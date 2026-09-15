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
 * Beberapa akun karyawan (role employee) untuk mencoba portal mandiri.
 *
 *     php artisan db:seed --class=EmployeeUserSeeder
 *
 * Satu akun per departemen dengan entitas kerja yang berbeda-beda. Akun
 * terakhir sengaja tanpa data diri supaya banner "lengkapi data diri"
 * bisa dicoba. Aman dijalankan berulang (updateOrCreate berkunci email/NIK).
 */
class EmployeeUserSeeder extends Seeder
{
    private const PASSWORD = 'Karyawan#2026';

    /**
     * @var list<array<string, mixed>>
     */
    private const ACCOUNTS = [
        [
            'nik' => 'RCK-1001',
            'name' => 'Andi Pratama',
            'email' => 'andi@ricklean.co.id',
            'phone' => '081234560001',
            'department' => 'SALES',
            'position' => 'Sales Consultant',
            'type' => 'PKWT12',
            'personal' => [
                'ktp_number' => '7371011204950001',
                'birth_place' => 'Makassar',
                'birth_date' => '1995-04-12',
                'gender' => 'male',
                'religion' => 'islam',
                'marital_status' => 'kawin',
                'dependents_count' => 2,
                'ktp_address' => 'Jl. Perintis Kemerdekaan No. 10, Tamalanrea, Makassar',
                'domicile_address' => 'Jl. Perintis Kemerdekaan No. 10, Tamalanrea, Makassar',
                'emergency_contact_name' => 'Siti Aminah',
                'emergency_contact_relation' => 'Istri',
                'emergency_contact_phone' => '081298760001',
            ],
        ],
        [
            'nik' => 'RCK-1002',
            'name' => 'Rina Kartika',
            'email' => 'rina@ricklean.co.id',
            'phone' => '081234560002',
            'department' => 'AFTERSALES',
            'position' => 'Service Advisor',
            'type' => 'PKWT12',
            'personal' => [
                'ktp_number' => '7371025508980002',
                'birth_place' => 'Gowa',
                'birth_date' => '1998-08-15',
                'gender' => 'female',
                'religion' => 'kristen',
                'marital_status' => 'belum_kawin',
                'dependents_count' => 0,
                'ktp_address' => 'Jl. Poros Malino No. 21, Sungguminasa, Gowa',
                'domicile_address' => 'Jl. Hertasning Baru No. 7, Makassar',
                'emergency_contact_name' => 'Yohanes Kartika',
                'emergency_contact_relation' => 'Orang tua',
                'emergency_contact_phone' => '081298760002',
            ],
        ],
        [
            'nik' => 'RCK-1003',
            'name' => 'Muh. Fajar Ramadhan',
            'email' => 'fajar@ricklean.co.id',
            'phone' => '081234560003',
            'department' => 'FAT',
            'position' => 'Finance Staff',
            'type' => 'PROB3',
            'personal' => [
                'ktp_number' => '7371030112000003',
                'birth_place' => 'Maros',
                'birth_date' => '2000-12-01',
                'gender' => 'male',
                'religion' => 'islam',
                'marital_status' => 'belum_kawin',
                'dependents_count' => 0,
                'ktp_address' => 'Jl. Poros Maros No. 3, Turikale, Maros',
                'domicile_address' => 'Jl. Urip Sumoharjo No. 45, Makassar',
                'emergency_contact_name' => 'Hasnah',
                'emergency_contact_relation' => 'Orang tua',
                'emergency_contact_phone' => '081298760003',
            ],
        ],
        [
            'nik' => 'RCK-1004',
            'name' => 'Nurul Hidayah',
            'email' => 'nurul@ricklean.co.id',
            'phone' => '081234560004',
            'department' => 'MKT',
            'position' => 'Digital Marketing',
            'type' => 'MITRA',
            'personal' => [
                'ktp_number' => '7371046703970004',
                'birth_place' => 'Makassar',
                'birth_date' => '1997-03-27',
                'gender' => 'female',
                'religion' => 'islam',
                'marital_status' => 'kawin',
                'dependents_count' => 1,
                'ktp_address' => 'Jl. Pengayoman No. 12, Panakkukang, Makassar',
                'domicile_address' => 'Jl. Pengayoman No. 12, Panakkukang, Makassar',
                'emergency_contact_name' => 'Rahmat Hidayat',
                'emergency_contact_relation' => 'Suami',
                'emergency_contact_phone' => '081298760004',
            ],
        ],
        [
            'nik' => 'RCK-1005',
            'name' => 'Budi Santoso',
            'email' => 'budi@ricklean.co.id',
            'phone' => null,
            'department' => 'OPS',
            'position' => 'Staf Operasional',
            'type' => 'PROB3',
            // Data diri sengaja kosong — untuk mencoba alur pengisian.
            'personal' => [],
        ],
    ];

    public function run(): void
    {
        $today = CarbonImmutable::today();

        foreach (self::ACCOUNTS as $account) {
            $type = $this->employmentType($account['type']);

            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => 'employee',
                    'password' => Hash::make(self::PASSWORD),
                    'must_change_password' => false,
                ],
            );

            Employee::updateOrCreate(
                ['nik' => $account['nik']],
                [
                    'user_id' => $user->id,
                    'employment_type_id' => $type->id,
                    'department_id' => $this->department($account['department'])->id,
                    'full_name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => $account['phone'],
                    'position' => $account['position'],
                    'join_date' => $today->subMonths(2)->toDateString(),
                    'contract_start' => $today->subMonths(2)->toDateString(),
                    'contract_end' => $type->duration_months
                        ? $today->subMonths(2)->addMonths($type->duration_months)->toDateString()
                        : null,
                    'basic_salary' => $type->category === 'mitra' ? 0 : 4_500_000,
                    'status' => 'active',
                    ...$account['personal'],
                ],
            );
        }

        $this->command?->table(
            ['Email', 'Kata sandi'],
            array_map(fn (array $account) => [$account['email'], self::PASSWORD], self::ACCOUNTS),
        );
    }

    private function employmentType(string $code): EmploymentType
    {
        $definitions = [
            'PROB3' => ['name' => 'Probation 3 Bulan', 'category' => 'probation', 'duration_months' => 3, 'annual_leave_quota' => 3],
            'PKWT12' => ['name' => 'PKWT 12 Bulan', 'category' => 'pkwt', 'duration_months' => 12, 'annual_leave_quota' => 12],
            'MITRA' => ['name' => 'Mitra / Freelance', 'category' => 'mitra', 'duration_months' => null, 'annual_leave_quota' => 12],
        ];

        return EmploymentType::firstOrCreate(
            ['code' => $code],
            $definitions[$code] + ['is_leave_eligible' => true, 'is_bpjs_eligible' => true],
        );
    }

    /**
     * Departemen ditanam migrasi; firstOrCreate hanya jaring pengaman.
     */
    private function department(string $code): Department
    {
        $names = [
            'SALES' => 'Sales',
            'AFTERSALES' => 'Aftersales',
            'FAT' => 'FAT',
            'MKT' => 'Marketing',
            'OPS' => 'Operasional',
        ];

        return Department::firstOrCreate(
            ['code' => $code],
            ['name' => $names[$code], 'location' => 'Makassar'],
        );
    }
}
