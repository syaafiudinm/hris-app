<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\MitraPayrollSchema;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


/**
 * One-Click Hired Conversion — mengubah pelamar menjadi karyawan/mitra
 * dalam satu transaksi database.
 */
class HiredConversionService
{
    public function __construct(
        private AccountProvisioningService $accountService,
    ) {}

    /**
     * Convert an applicant to an employee record.
     *
     * @param  array<string, mixed>  $conversionData
     * @param  array<string, mixed>|null  $mitraSchemaData  Skema mitra (jika kategori mitra)
     * @return Employee
     */
    public function convert(
        Applicant $applicant,
        array $conversionData,
        ?array $mitraSchemaData = null,
        ?string $changedBy = null,
    ): Employee {
        if ($applicant->converted_employee_id) {
            throw ValidationException::withMessages([
                'applicant' => 'Pelamar ini sudah dikonversi menjadi karyawan.',
            ]);
        }

        $employmentType = EmploymentType::findOrFail($conversionData['employment_type_id']);

        return DB::transaction(function () use ($applicant, $conversionData, $employmentType, $mitraSchemaData, $changedBy) {
            $contractStart = now();

            // Durasi entitas jadi acuan utama; bila entitas tidak punya durasi
            // (mitra), pakai tanggal akhir yang diisi HR pada form konversi.
            $contractEnd = $employmentType->duration_months
                ? $contractStart->copy()->addMonths($employmentType->duration_months)
                : ($conversionData['contract_end'] ?? null);

            $employee = Employee::create([
                'employment_type_id' => $employmentType->id,
                'department_id' => $conversionData['department_id'] ?? null,
                'nik' => $this->generateNik(),
                'full_name' => $applicant->full_name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'position' => $conversionData['position'] ?? null,
                'join_date' => $contractStart,
                'contract_start' => $contractStart,
                'contract_end' => $contractEnd,
                'basic_salary' => $conversionData['basic_salary'] ?? 0,
                'status' => 'active',
            ]);

            // Untuk mitra, buat skema pembayaran custom.
            if ($employmentType->category === 'mitra' && $mitraSchemaData) {
                MitraPayrollSchema::create(array_merge($mitraSchemaData, [
                    'employee_id' => $employee->id,
                ]));
            }

            // Auto-provision akun login jika email tersedia.
            if ($employee->email) {
                $this->accountService->provision($employee);
            }

            // Update applicant — catat converted_employee_id dan stage.
            $applicant->update(['converted_employee_id' => $employee->id]);
            $applicant->recordStageChange($applicant->stage, 'hired', $changedBy);

            return $employee;
        });
    }

    /**
     * Bangkitkan NIK unik.
     *
     * Nomor urut diambil dari NIK tertinggi yang benar-benar ada, bukan dari
     * id terakhir — sebab HR bisa menginput NIK manual sehingga urutan id dan
     * urutan NIK tidak selalu sejalan. Loop menjaga agar tetap aman ketika
     * ada lompatan nomor.
     */
    private function generateNik(string $prefix = 'EMP'): string
    {
        // Nomor urut dihitung di PHP agar sama persis di MySQL maupun SQLite
        // (fungsi SUBSTRING/CAST keduanya berbeda dialek).
        $highest = Employee::where('nik', 'like', $prefix.'-%')
            ->pluck('nik')
            ->map(fn (string $nik) => (int) substr($nik, strlen($prefix) + 1))
            ->max() ?? 0;

        $next = $highest + 1;

        // Batas percobaan mencegah loop tak berujung bila data NIK kacau.
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $candidate = sprintf('%s-%04d', $prefix, $next + $attempt);

            if (! Employee::where('nik', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'nik' => 'Tidak dapat membuat NIK unik. Periksa penomoran data karyawan.',
        ]);
    }
}
