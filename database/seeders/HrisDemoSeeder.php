<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobVacancy;
use App\Models\LeaveRequest;
use App\Models\MitraPayrollSchema;
use App\Models\OfficeLocation;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HrisDemoSeeder extends Seeder
{
    private PayrollCalculator $calculator;

    /** @var array<string, EmploymentType> */
    private array $types = [];

    /** @var array<string, Department> */
    private array $departments = [];

    public function __construct()
    {
        $this->calculator = new PayrollCalculator;
    }

    public function run(): void
    {
        mt_srand(2026);

        $this->seedEmploymentTypes();
        $this->seedDepartments();
        $this->seedOfficeLocations();
        $employees = $this->seedEmployees();
        $this->seedPayrolls($employees);
        $this->seedAttendances($employees);
        $this->seedLeaveRequests($employees);
        $this->seedRecruitment();
    }

    private function seedEmploymentTypes(): void
    {
        $definitions = [
            ['code' => 'PROB3', 'name' => 'Probation 3 Bulan', 'category' => 'probation', 'duration_months' => 3, 'is_leave_eligible' => false, 'is_bpjs_eligible' => false, 'annual_leave_quota' => 0],
            ['code' => 'PKWT3', 'name' => 'PKWT 3 Bulan', 'category' => 'pkwt', 'duration_months' => 3, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 3],
            ['code' => 'PKWT6', 'name' => 'PKWT 6 Bulan', 'category' => 'pkwt', 'duration_months' => 6, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 6],
            ['code' => 'PKWT12', 'name' => 'PKWT 12 Bulan', 'category' => 'pkwt', 'duration_months' => 12, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 12],
            ['code' => 'MITRA', 'name' => 'Mitra / Freelance', 'category' => 'mitra', 'duration_months' => null, 'is_leave_eligible' => false, 'is_bpjs_eligible' => false, 'annual_leave_quota' => 0],
        ];

        foreach ($definitions as $definition) {
            $this->types[$definition['code']] = EmploymentType::updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            );
        }
    }

    private function seedDepartments(): void
    {
        $definitions = [
            ['code' => 'TECH', 'name' => 'Teknologi', 'location' => 'Jakarta'],
            ['code' => 'OPS', 'name' => 'Operasional', 'location' => 'Jakarta'],
            ['code' => 'FIN', 'name' => 'Keuangan', 'location' => 'Jakarta'],
            ['code' => 'MKT', 'name' => 'Marketing', 'location' => 'Bandung'],
            ['code' => 'HRD', 'name' => 'Human Capital', 'location' => 'Jakarta'],
        ];

        foreach ($definitions as $definition) {
            $this->departments[$definition['code']] = Department::updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            );
        }
    }

    private function seedOfficeLocations(): void
    {
        $definitions = [
            ['name' => 'Kantor Pusat Jakarta', 'latitude' => -6.2088, 'longitude' => 106.8456, 'radius_meters' => 200],
            ['name' => 'Kantor Bandung', 'latitude' => -6.9175, 'longitude' => 107.6191, 'radius_meters' => 150],
        ];

        foreach ($definitions as $definition) {
            OfficeLocation::updateOrCreate(['name' => $definition['name']], $definition);
        }
    }

    /**
     * @return EloquentCollection<int, Employee>
     */
    private function seedEmployees()
    {
        $firstNames = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eka', 'Fajar', 'Gita', 'Hendra', 'Indah', 'Joko', 'Kartika', 'Lukman', 'Maya', 'Nanda', 'Oki', 'Putri', 'Rangga', 'Sari', 'Tomi', 'Umar', 'Vina', 'Wahyu', 'Yudha', 'Zahra'];
        $lastNames = ['Pratama', 'Wijaya', 'Santoso', 'Nugroho', 'Halim', 'Kusuma', 'Saputra', 'Ramadhan', 'Lestari', 'Anggraini', 'Firmansyah', 'Maulana'];

        $positions = [
            'TECH' => ['Backend Engineer', 'Frontend Engineer', 'QA Engineer', 'DevOps Engineer', 'Product Designer'],
            'OPS' => ['Operations Staff', 'Warehouse Supervisor', 'Logistics Officer'],
            'FIN' => ['Finance Staff', 'Accounting Officer', 'Tax Analyst'],
            'MKT' => ['Digital Marketing', 'Content Strategist', 'Sales Executive'],
            'HRD' => ['HR Generalist', 'Recruiter', 'People Ops'],
        ];

        // Komposisi entitas kerja: mayoritas PKWT, sisanya probation & mitra.
        $composition = array_merge(
            array_fill(0, 8, 'PROB3'),
            array_fill(0, 5, 'PKWT3'),
            array_fill(0, 9, 'PKWT6'),
            array_fill(0, 22, 'PKWT12'),
            array_fill(0, 14, 'MITRA'),
        );

        $today = CarbonImmutable::now();
        $employees = new EloquentCollection;
        $counter = 1;

        foreach ($composition as $index => $typeCode) {
            $type = $this->types[$typeCode];
            $deptCode = array_rand($positions);
            $department = $this->departments[$deptCode];

            $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];

            $isMitra = $type->category === 'mitra';
            $duration = $type->duration_months ?? mt_rand(3, 12);

            // Sebarkan tanggal mulai kontrak sehingga sebagian berakhir dalam 30 hari.
            $startOffset = mt_rand(0, $duration * 30 - 5);
            $contractStart = $today->subDays($startOffset);
            $contractEnd = $contractStart->addMonths($duration);

            $status = $contractEnd->isPast() ? 'expired' : 'active';

            $salary = $isMitra
                ? 0
                : match ($type->category) {
                    'probation' => mt_rand(45, 75) * 100_000,
                    default => mt_rand(60, 220) * 100_000,
                };

            $employee = Employee::create([
                'employment_type_id' => $type->id,
                'department_id' => $department->id,
                'nik' => sprintf('EMP-%04d', $counter++),
                'full_name' => $name,
                'email' => Str::slug($name, '.').$index.'@perusahaan.co.id',
                'phone' => '08'.mt_rand(1000000000, 9999999999),
                'position' => $positions[$deptCode][array_rand($positions[$deptCode])],
                'join_date' => $contractStart,
                'contract_start' => $contractStart,
                'contract_end' => $contractEnd,
                'basic_salary' => $salary,
                'status' => $status,
            ]);

            if ($isMitra) {
                $this->attachMitraSchema($employee);
            }

            $employees->push($employee);
        }

        // Akun HR (Super Admin) sebagai pemilik dashboard.
        $hr = Employee::create([
            'employment_type_id' => $this->types['PKWT12']->id,
            'department_id' => $this->departments['HRD']->id,
            'nik' => sprintf('EMP-%04d', $counter),
            'full_name' => 'Syaafiudin M',
            'email' => 'hr@perusahaan.co.id',
            'position' => 'HR Manager',
            'join_date' => $today->subYears(2),
            'contract_start' => $today->subMonths(4),
            'contract_end' => $today->addMonths(8),
            'basic_salary' => 18_500_000,
            'status' => 'active',
        ]);

        $this->attachAccount($hr, 'super_admin');

        // Satu manager (atasan) dan satu karyawan self-service untuk uji RBAC.
        $manager = $employees->firstWhere(fn (Employee $e) => $e->status === 'active' && ! $e->isMitra());
        if ($manager) {
            $this->attachAccount($manager, 'manager', 'manager@perusahaan.co.id');
        }

        $staff = $employees->first(
            fn (Employee $e) => $e->status === 'active' && ! $e->isMitra() && $e->isNot($manager),
        );
        if ($staff) {
            $this->attachAccount($staff, 'employee', 'karyawan@perusahaan.co.id');
        }

        $mitra = $employees->first(fn (Employee $e) => $e->status === 'active' && $e->isMitra());
        if ($mitra) {
            $this->attachAccount($mitra, 'employee', 'mitra@perusahaan.co.id');
        }

        return $employees;
    }

    /**
     * Buat akun login untuk seorang karyawan. Password demo: "password".
     */
    private function attachAccount(Employee $employee, string $role, ?string $email = null): void
    {
        $user = User::create([
            'name' => $employee->full_name,
            'email' => $email ?? $employee->email,
            'role' => $role,
            'password' => Hash::make('password'),
        ]);

        $employee->update(['user_id' => $user->id, 'email' => $user->email]);
    }

    private function attachMitraSchema(Employee $employee): void
    {
        $schemas = [
            ['schema_type' => 'hourly', 'rate_per_unit' => mt_rand(75, 250) * 1000, 'unit_label' => 'jam'],
            ['schema_type' => 'daily', 'rate_per_unit' => mt_rand(350, 900) * 1000, 'unit_label' => 'hari'],
            ['schema_type' => 'fixed_project', 'rate_per_unit' => mt_rand(8, 45) * 1_000_000, 'unit_label' => 'proyek'],
            ['schema_type' => 'milestone', 'rate_per_unit' => mt_rand(20, 80) * 1_000_000, 'unit_label' => 'milestone'],
            ['schema_type' => 'unit', 'rate_per_unit' => mt_rand(50, 400) * 1000, 'unit_label' => 'artikel'],
        ];

        $schema = $schemas[array_rand($schemas)];
        $taxSchemes = ['pph21_tidak_berkesinambungan', 'pph21_berkesinambungan', 'pph23', 'bebas_pajak'];
        $taxScheme = $taxSchemes[array_rand($taxSchemes)];

        MitraPayrollSchema::create([
            'employee_id' => $employee->id,
            'schema_type' => $schema['schema_type'],
            'rate_per_unit' => $schema['rate_per_unit'],
            'unit_label' => $schema['unit_label'],
            'tax_scheme' => $taxScheme,
            'custom_tax_percentage' => match ($taxScheme) {
                'pph23' => 2,
                'bebas_pajak' => 0,
                default => 2.5,
            },
            'components' => $schema['schema_type'] === 'milestone'
                ? ['milestones' => [['name' => 'Phase 1', 'percentage' => 30], ['name' => 'Phase 2', 'percentage' => 70]]]
                : ['transport_allowance' => mt_rand(0, 1) ? 500_000 : 0],
        ]);
    }

    /**
     * @param  EloquentCollection<int, Employee>  $employees
     */
    private function seedPayrolls($employees): void
    {
        $employees->load('employmentType', 'mitraPayrollSchema');
        $period = CarbonImmutable::now()->startOfMonth();
        $rows = [];

        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $current = $period->subMonths($monthsAgo);

            foreach ($employees as $employee) {
                // Lewati periode sebelum kontrak dimulai.
                if ($employee->contract_start->greaterThan($current->endOfMonth())) {
                    continue;
                }

                if ($employee->isMitra()) {
                    $schema = $employee->mitraPayrollSchema;
                    if (! $schema) {
                        continue;
                    }

                    $quantity = match ($schema->schema_type) {
                        'hourly' => mt_rand(60, 170),
                        'daily' => mt_rand(8, 22),
                        'unit' => mt_rand(4, 30),
                        'milestone' => mt_rand(30, 70) / 100,
                        default => 1,
                    };

                    $amounts = $this->calculator->calculateMitra(
                        $schema,
                        $quantity,
                        bonus: mt_rand(0, 3) === 0 ? 1_000_000 : 0,
                        penalty: mt_rand(0, 9) === 0 ? 500_000 : 0,
                    );
                } else {
                    $basic = (float) $employee->basic_salary;
                    $amounts = $this->calculator->calculateEmployee(
                        $employee,
                        $basic,
                        allowance: $basic * 0.1,
                        overtime: mt_rand(0, 1) ? mt_rand(0, 12) * 85_000 : 0,
                    );
                }

                $rows[] = array_merge($amounts, [
                    'employee_id' => $employee->id,
                    'period_year' => $current->year,
                    'period_month' => $current->month,
                    'status' => 'paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Payroll::insert($chunk);
        }
    }

    /**
     * @param  EloquentCollection<int, Employee>  $employees
     */
    private function seedAttendances($employees): void
    {
        $today = CarbonImmutable::now();
        $rows = [];

        $activeEmployees = $employees->where('status', 'active');

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $date = $today->subDays($daysAgo);

            if ($date->isWeekend()) {
                continue;
            }

            foreach ($activeEmployees as $employee) {
                if ($employee->contract_start->greaterThan($date)) {
                    continue;
                }

                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 80 => 'present',
                    $roll <= 92 => 'late',
                    $roll <= 96 => 'leave',
                    default => 'absent',
                };

                $lateMinutes = $status === 'late' ? mt_rand(5, 75) : 0;
                $clockIn = in_array($status, ['present', 'late'], true)
                    ? $date->setTime(8, 0)->addMinutes($lateMinutes)
                    : null;
                $clockOut = $clockIn?->addMinutes(mt_rand(480, 620));

                $rows[] = [
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'clock_in_lat' => $clockIn ? -6.2 - mt_rand(0, 900) / 100000 : null,
                    'clock_in_long' => $clockIn ? 106.8 + mt_rand(0, 900) / 100000 : null,
                    'clock_in_photo' => $clockIn ? 'attendance/'.Str::uuid().'.jpg' : null,
                    'is_fake_gps' => $clockIn && mt_rand(1, 120) === 1,
                    'status' => $status,
                    'work_minutes' => $clockIn && $clockOut ? $clockIn->diffInMinutes($clockOut) : 0,
                    'late_minutes' => $lateMinutes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Attendance::insert($chunk);
        }
    }

    /**
     * @param  EloquentCollection<int, Employee>  $employees
     */
    private function seedLeaveRequests($employees): void
    {
        // Hanya entitas yang berhak cuti yang punya pengajuan cuti tahunan —
        // sesuai rule engine, pengajuan Probation/Mitra ditolak di level API.
        $eligible = $employees->filter(fn (Employee $employee) => $employee->isLeaveEligible());
        $today = CarbonImmutable::now();

        foreach ($eligible as $employee) {
            for ($i = mt_rand(0, 2); $i > 0; $i--) {
                $start = $today->addDays(mt_rand(-40, 25));
                $days = mt_rand(1, 4);

                LeaveRequest::create([
                    'employee_id' => $employee->id,
                    'leave_type' => ['annual', 'sick', 'unpaid', 'special'][array_rand(['annual', 'sick', 'unpaid', 'special'])],
                    'start_date' => $start,
                    'end_date' => $start->addDays($days - 1),
                    'total_days' => $days,
                    'reason' => 'Keperluan pribadi',
                    'status' => ['pending', 'approved', 'approved', 'rejected'][mt_rand(0, 3)],
                ]);
            }
        }
    }

    private function seedRecruitment(): void
    {
        $vacancies = [
            ['title' => 'Senior Backend Engineer', 'offered_category' => 'pkwt', 'dept' => 'TECH', 'quota' => 2],
            ['title' => 'UI/UX Designer (Freelance)', 'offered_category' => 'mitra', 'dept' => 'TECH', 'quota' => 1],
            ['title' => 'Finance Staff', 'offered_category' => 'probation', 'dept' => 'FIN', 'quota' => 1],
            ['title' => 'Digital Marketing Specialist', 'offered_category' => 'pkwt', 'dept' => 'MKT', 'quota' => 3],
            ['title' => 'Content Writer (Per Artikel)', 'offered_category' => 'mitra', 'dept' => 'MKT', 'quota' => 4],
            ['title' => 'Warehouse Supervisor', 'offered_category' => 'probation', 'dept' => 'OPS', 'quota' => 1],
        ];

        $firstNames = ['Adit', 'Bella', 'Cahya', 'Dimas', 'Erlangga', 'Fitri', 'Galuh', 'Hana', 'Irfan', 'Jasmine'];
        $lastNames = ['Prasetyo', 'Anwar', 'Setiawan', 'Rahmawati', 'Hidayat', 'Salsabila'];

        $stages = ['applied', 'applied', 'applied', 'screening', 'screening', 'interview', 'interview', 'offering', 'hired', 'rejected'];

        foreach ($vacancies as $index => $definition) {
            $vacancy = JobVacancy::create([
                'department_id' => $this->departments[$definition['dept']]->id,
                'title' => $definition['title'],
                'offered_category' => $definition['offered_category'],
                'location' => $this->departments[$definition['dept']]->location,
                'quota' => $definition['quota'],
                'status' => 'open',
                'published_at' => CarbonImmutable::now()->subDays(mt_rand(5, 60)),
            ]);

            foreach (range(1, mt_rand(6, 18)) as $n) {
                $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];

                Applicant::create([
                    'job_vacancy_id' => $vacancy->id,
                    'full_name' => $name,
                    'email' => Str::slug($name, '.').$index.$n.'@mail.com',
                    'phone' => '08'.mt_rand(1000000000, 9999999999),
                    'stage' => $stages[array_rand($stages)],
                ]);
            }
        }
    }
}
