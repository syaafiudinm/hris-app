<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\MitraPayrollSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One-Click Hired Conversion — mengubah pelamar menjadi karyawan/mitra
 * dalam satu transaksi database.
 */
class HiredConversionService
{
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
            $contractEnd = $employmentType->duration_months
                ? $contractStart->copy()->addMonths($employmentType->duration_months)
                : null;

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

            // Update applicant — catat converted_employee_id dan stage.
            $applicant->update(['converted_employee_id' => $employee->id]);
            $applicant->recordStageChange($applicant->stage, 'hired', $changedBy);

            return $employee;
        });
    }

    /**
     * Generate NIK unik berdasarkan ID terakhir.
     */
    private function generateNik(): string
    {
        $last = Employee::orderByDesc('id')->value('id') ?? 0;

        return sprintf('EMP-%04d', $last + 1);
    }
}
