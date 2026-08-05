<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun awal: satu pengguna untuk tiap role.
 *
 * Dipisahkan dari HrisDemoSeeder supaya aman dijalankan di server produksi
 * yang sudah berisi data sungguhan:
 *
 *     php artisan db:seed --class=RoleAccountSeeder --force
 *
 * Seluruhnya memakai updateOrCreate berkunci email dan NIK, jadi menjalankan
 * ulang tidak menggandakan apa pun — perbaiki datanya di bawah lalu jalankan
 * lagi bila ada yang salah ketik.
 */
class RoleAccountSeeder extends Seeder
{
    /**
     * Kata sandi awal ketiga akun. **Ganti setelah login pertama** —
     * lihat catatan di bawah kelas ini.
     */
    private const PASSWORD = 'Ricklean#2026';

    /**
     * Ubah baris berikut sesuai kebutuhan sebelum dijalankan.
     *
     * @var list<array{role: string, name: string, email: string, nik: string, position: string}>
     */
    private const ACCOUNTS = [
        [
            'role' => 'super_admin',
            'name' => 'HR Ricklean',
            'email' => 'hr@ricklean.co.id',
            'nik' => 'RCK-0001',
            'position' => 'HR Manager',
        ],
        [
            'role' => 'manager',
            'name' => 'Manager Operasional',
            'email' => 'manager@ricklean.co.id',
            'nik' => 'RCK-0002',
            'position' => 'Kepala Operasional',
        ],
        [
            'role' => 'employee',
            'name' => 'Staf Operasional',
            'email' => 'staf@ricklean.co.id',
            'nik' => 'RCK-0003',
            'position' => 'Staf Operasional',
        ],
    ];

    public function run(): void
    {
        // Akun tanpa baris `employees` yang tertaut akan kena 403 di seluruh
        // portal mandiri, jadi keduanya selalu dibuat berpasangan.
        $type = $this->employmentType();
        $department = $this->department();
        $today = CarbonImmutable::today();

        foreach (self::ACCOUNTS as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'password' => Hash::make(self::PASSWORD),
                ],
            );

            Employee::updateOrCreate(
                ['nik' => $account['nik']],
                [
                    'user_id' => $user->id,
                    'employment_type_id' => $type->id,
                    'department_id' => $department->id,
                    'full_name' => $account['name'],
                    'email' => $account['email'],
                    'position' => $account['position'],
                    'join_date' => $today->toDateString(),
                    'contract_start' => $today->toDateString(),
                    'contract_end' => $type->duration_months
                        ? $today->addMonths($type->duration_months)->toDateString()
                        : null,
                    'basic_salary' => 5_000_000,
                    'status' => 'active',
                ],
            );
        }

        $this->command?->table(
            ['Role', 'Email', 'Kata sandi'],
            array_map(
                fn (array $account) => [$account['role'], $account['email'], self::PASSWORD],
                self::ACCOUNTS,
            ),
        );

        $this->command?->warn('Ganti kata sandi ketiga akun setelah login pertama.');
    }

    /**
     * Entitas kerja untuk akun awal. Dibuat bila belum ada supaya seeder ini
     * tetap jalan pada database yang belum pernah diisi HrisDemoSeeder.
     */
    private function employmentType(): EmploymentType
    {
        return EmploymentType::firstOrCreate(
            ['code' => 'PKWT12'],
            [
                'name' => 'PKWT 12 Bulan',
                'category' => 'pkwt',
                'duration_months' => 12,
                'is_leave_eligible' => true,
                'is_bpjs_eligible' => true,
                'annual_leave_quota' => 12,
            ],
        );
    }

    private function department(): Department
    {
        return Department::firstOrCreate(
            ['code' => 'OPS'],
            ['name' => 'Operasional', 'location' => 'Makassar'],
        );
    }
}
