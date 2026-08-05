<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\EmploymentType;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\JobVacancy;
use App\Models\KnowledgeDocument;
use App\Models\LeaveRequest;
use App\Models\MitraPayrollSchema;
use App\Models\OfficeLocation;
use App\Models\Payroll;
use App\Models\SalesProduct;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\ExitService;
use App\Services\PayrollCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        $this->seedKnowledgeCenter();
        $this->seedExits();
        $this->seedSales();
        $this->seedInventory($employees);
    }

    /**
     * Konfigurasi baku skema penjualan mitra.
     *
     * Angka mengikuti kebijakan perusahaan pengguna; seluruhnya dapat diubah
     * HR per mitra lewat halaman Skema Mitra.
     *
     * @return array<string, mixed>
     */
    public static function salesSchemeDefaults(): array
    {
        return [
            'monthly_allowance' => 1_000_000,   // uang makan & transport per bulan
            'working_days' => 26,               // 1 bulan = 26 hari kerja
            'ump_reference' => 3_921_000,       // UMP Sulawesi Selatan
            'bonus_tiers' => [
                ['units' => 2, 'percentage' => 50],
                ['units' => 3, 'percentage' => 75],
                ['units' => 4, 'percentage' => 100],
            ],
            'incentive_tax_base_percentage' => 50,  // 50% dari insentif
            'incentive_tax_rate' => 2.5,            // x 2,5%
            // Dasar upah pelaporan BPJS mitra — penghasilannya variabel,
            // jadi dipatok pada UMP.
            'bpjs_wage_base' => 3_921_000,
        ];
    }

    /**
     * Katalog produk, mitra bertipe penjualan, dan catatan unit terjual.
     */
    private function seedSales(): void
    {
        $products = [
            ['code' => 'ex2', 'name' => 'EX2', 'incentive_amount' => 500_000],
            ['code' => 'ex5', 'name' => 'EX5', 'incentive_amount' => 2_000_000],
            ['code' => 'starray', 'name' => 'Starray', 'incentive_amount' => 3_000_000],
        ];

        foreach ($products as $product) {
            SalesProduct::updateOrCreate(['code' => $product['code']], $product);
        }

        $catalog = SalesProduct::orderBy('id')->get();

        // Pindahkan sebagian mitra ke skema penjualan.
        $salesMitra = Employee::active()
            ->whereHas('employmentType', fn ($query) => $query->where('category', 'mitra'))
            ->limit(5)
            ->get();

        $period = CarbonImmutable::now();

        foreach ($salesMitra as $index => $mitra) {
            MitraPayrollSchema::updateOrCreate(
                ['employee_id' => $mitra->id],
                [
                    'schema_type' => 'sales',
                    'rate_per_unit' => 0,
                    'unit_label' => 'unit',
                    'tax_scheme' => 'pph21_tidak_berkesinambungan',
                    'custom_tax_percentage' => 2.5,
                    'components' => self::salesSchemeDefaults(),
                ],
            );

            // Sebar penjualan agar tiap tier bonus terwakili di data demo.
            $quantities = [
                [0, 0, 0],  // tanpa penjualan
                [1, 1, 0],  // 2 unit  -> bonus 50%
                [1, 1, 1],  // 3 unit  -> bonus 75%
                [2, 1, 1],  // 4 unit  -> bonus 100%
                [0, 1, 2],  // 3 unit  -> bonus 75%
            ][$index] ?? [0, 0, 0];

            // Dicatat untuk bulan berjalan dan bulan lalu — absensi demo
            // berada pada 30 hari terakhir, jadi bulan lalu yang memperlihatkan
            // prorata kehadiran secara utuh.
            foreach ([$period, $period->subMonth()] as $target) {
                foreach ($catalog as $position => $product) {
                    if (($quantities[$position] ?? 0) < 1) {
                        continue;
                    }

                    SalesRecord::updateOrCreate(
                        [
                            'employee_id' => $mitra->id,
                            'sales_product_id' => $product->id,
                            'period_year' => $target->year,
                            'period_month' => $target->month,
                        ],
                        ['quantity' => $quantities[$position]],
                    );
                }
            }
        }

        // Mitra non-penjualan tetap perlu dasar BPJS karena iurannya kini
        // ditanggung perusahaan.
        MitraPayrollSchema::where('schema_type', '!=', 'sales')
            ->get()
            ->each(function (MitraPayrollSchema $schema) {
                $components = $schema->components ?? [];
                $components['bpjs_wage_base'] = 3_921_000;
                $schema->update(['components' => $components]);
            });
    }

    /**
     * Contoh proses keluar: satu masih draft, dua sudah tuntas dengan
     * paklaring terbit.
     */
    private function seedExits(): void
    {
        $hr = Employee::where('email', 'hr@perusahaan.co.id')->first();
        $service = new ExitService;
        $today = CarbonImmutable::now();

        // Ambil karyawan aktif yang tidak punya akun login, agar akun demo
        // tetap bisa dipakai masuk.
        $candidates = Employee::active()
            ->whereNull('user_id')
            ->whereDoesntHave('exit')
            ->inRandomOrder()
            ->limit(3)
            ->get();

        $definitions = [
            ['exit_type' => 'resign', 'complete' => true, 'reason' => 'Melanjutkan studi pascasarjana.', 'days' => 45],
            ['exit_type' => 'contract_end', 'complete' => true, 'reason' => 'Masa perjanjian kerja berakhir dan tidak diperpanjang.', 'days' => 20],
            ['exit_type' => 'resign', 'complete' => false, 'reason' => 'Pindah domisili ke luar kota.', 'days' => -14],
        ];

        foreach ($candidates as $index => $employee) {
            $definition = $definitions[$index] ?? $definitions[0];
            $lastDay = $today->subDays($definition['days']);

            $exit = EmployeeExit::create([
                'employee_id' => $employee->id,
                'exit_type' => $definition['exit_type'],
                'submitted_date' => $definition['exit_type'] === 'resign'
                    ? $lastDay->subDays(30)
                    : null,
                'last_working_date' => $lastDay,
                'reason' => $definition['reason'],
                'notes' => 'Serah terima pekerjaan selesai.',
                'status' => 'draft',
                'processed_by' => $hr?->id,
            ]);

            if ($definition['complete']) {
                $service->complete($exit);
            }
        }
    }

    /**
     * Bulletin pengumuman & repositori dokumen (Modul 5).
     */
    private function seedKnowledgeCenter(): void
    {
        $hr = Employee::where('email', 'hr@perusahaan.co.id')->first();
        $today = CarbonImmutable::now();

        $announcements = [
            [
                'title' => 'Penyesuaian Kebijakan Cuti Tahunan',
                'body' => "Mulai periode ini seluruh entitas kerja — Probation, PKWT, dan Mitra — memperoleh kuota cuti tahunan.\n\nKuota bersifat proporsional terhadap durasi kontrak dan dapat dilihat pada halaman Cuti & Izin Saya. Pengajuan tetap melalui persetujuan atasan masing-masing.",
                'category' => 'policy',
                'target_type' => 'all',
                'is_pinned' => true,
                'published_at' => $today->subDays(1),
            ],
            [
                'title' => 'Jadwal Penggajian Bulan Ini',
                'body' => "Proses payroll ditutup setiap tanggal 25. Pastikan seluruh timesheet dan pengajuan lembur sudah masuk sebelum tanggal tersebut.\n\nSlip gaji dapat diunduh melalui menu Slip Gaji Saya.",
                'category' => 'info',
                'target_type' => 'all',
                'is_pinned' => false,
                'published_at' => $today->subDays(4),
            ],
            [
                'title' => 'Wajib Clock-in Melalui Aplikasi',
                'body' => 'Absensi manual sudah tidak diterima. Gunakan menu Absensi Saya dengan GPS aktif dan foto selfie. Absensi di luar radius kantor akan ditolak sistem.',
                'category' => 'urgent',
                'target_type' => 'all',
                'is_pinned' => false,
                'published_at' => $today->subDays(9),
            ],
            [
                'title' => 'Standar Pelaporan Timesheet Mitra',
                'body' => 'Rekan Mitra dengan skema per jam atau per hari wajib melengkapi clock-in dan clock-out. Timesheet inilah yang menjadi dasar perhitungan pembayaran setiap periode.',
                'category' => 'info',
                'target_type' => 'employment_category',
                'target_category' => 'mitra',
                'is_pinned' => false,
                'published_at' => $today->subDays(6),
            ],
            [
                'title' => 'Sprint Review Divisi Teknologi',
                'body' => 'Sprint review diadakan setiap Jumat pukul 15.00 di ruang rapat lantai 3. Kehadiran seluruh anggota tim diharapkan.',
                'category' => 'info',
                'target_type' => 'department',
                'target_department_id' => $this->departments['TECH']->id,
                'is_pinned' => false,
                'published_at' => $today->subDays(2),
            ],
            [
                'title' => 'Draf Kebijakan Kerja Hibrida',
                'body' => 'Dokumen masih dalam pembahasan manajemen dan belum berlaku. Akan diumumkan setelah disahkan.',
                'category' => 'policy',
                'target_type' => 'all',
                'is_pinned' => false,
                'published_at' => null,
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::create($announcement + ['created_by' => $hr?->id]);
        }

        // Berkas contoh ditulis ke disk privat agar tombol unduh benar-benar
        // berfungsi saat demo.
        $documents = [
            ['title' => 'SOP Pengajuan Cuti & Izin', 'doc_type' => 'sop', 'target_type' => 'all', 'description' => 'Alur pengajuan, batas waktu, dan tingkat persetujuan.'],
            ['title' => 'Peraturan Perusahaan 2026', 'doc_type' => 'peraturan', 'target_type' => 'all', 'description' => 'Peraturan induk ketenagakerjaan yang berlaku di perusahaan.'],
            ['title' => 'Panduan Absensi GPS', 'doc_type' => 'panduan', 'target_type' => 'all', 'description' => 'Langkah clock-in, radius kantor, dan penanganan kendala.'],
            ['title' => 'Formulir Klaim Reimbursement', 'doc_type' => 'formulir', 'target_type' => 'all', 'description' => 'Diisi dan dilampirkan bersama bukti pembayaran.'],
            ['title' => 'SOP Onboarding Karyawan Baru', 'doc_type' => 'sop', 'target_type' => 'department', 'target_department_id' => $this->departments['HRD']->id, 'description' => 'Checklist internal tim Human Capital.'],
            ['title' => 'Panduan Kemitraan & Invoice', 'doc_type' => 'panduan', 'target_type' => 'employment_category', 'target_category' => 'mitra', 'description' => 'Ketentuan penagihan dan pajak untuk mitra.'],
        ];

        foreach ($documents as $index => $document) {
            $fileName = Str::slug($document['title']).'.pdf';
            $path = 'knowledge-documents/'.Str::uuid().'.pdf';

            // Konten PDF minimal yang valid supaya berkas dapat dibuka.
            $content = "%PDF-1.4\n% Dokumen contoh: {$document['title']}\n%%EOF\n";
            Storage::disk('local')->put($path, $content);

            KnowledgeDocument::create($document + [
                'file_path' => $path,
                'original_name' => $fileName,
                'file_size' => strlen($content),
                'mime_type' => 'application/pdf',
                'version' => '1.'.$index,
                'download_count' => mt_rand(0, 45),
                'uploaded_by' => $hr?->id,
            ]);
        }
    }

    private function seedEmploymentTypes(): void
    {
        // Kebijakan perusahaan pengguna:
        //  - ketiga entitas berhak cuti tahunan, kuota proporsional durasi kontrak;
        //  - ketiga entitas didaftarkan BPJS, dan iurannya ditanggung penuh
        //    perusahaan (porsi pekerja tidak dipotong dari take home pay).
        // Semuanya dapat diubah HR lewat halaman Entitas Kerja.
        $definitions = [
            ['code' => 'PROB3', 'name' => 'Probation 3 Bulan', 'category' => 'probation', 'duration_months' => 3, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 3],
            ['code' => 'PKWT3', 'name' => 'PKWT 3 Bulan', 'category' => 'pkwt', 'duration_months' => 3, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 3],
            ['code' => 'PKWT6', 'name' => 'PKWT 6 Bulan', 'category' => 'pkwt', 'duration_months' => 6, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 6],
            ['code' => 'PKWT12', 'name' => 'PKWT 12 Bulan', 'category' => 'pkwt', 'duration_months' => 12, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 12],
            ['code' => 'MITRA', 'name' => 'Mitra / Freelance', 'category' => 'mitra', 'duration_months' => null, 'is_leave_eligible' => true, 'is_bpjs_eligible' => true, 'annual_leave_quota' => 12],
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
        // Lokasi kantor sebenarnya. Belum ada UI pengelolaannya, jadi titik ini
        // adalah satu-satunya sumber kebenaran geofence absensi — ubah di sini
        // (dan lewat migrasi) bila kantor pindah atau bertambah.
        $definitions = [
            [
                'name' => 'Kantor Geely Ricklean Makassar',
                'latitude' => -5.1553330,
                'longitude' => 119.4237384,
                'radius_meters' => 150,
            ],
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

                // Sebagian kecil absen memakai opsi unggah foto (kerja
                // lapangan / kamera bermasalah). Yang belum lewat dua hari
                // sengaja dibiarkan pending agar antrean verifikasi HR terisi.
                $isUpload = $clockIn && mt_rand(1, 100) <= 12;
                $outsideRadius = $isUpload && mt_rand(1, 100) <= 70;

                $rows[] = [
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'clock_in_lat' => $clockIn ? -5.1553330 - mt_rand(0, 90) / 100000 : null,
                    'clock_in_long' => $clockIn ? 119.4237384 + mt_rand(0, 90) / 100000 : null,
                    'clock_in_photo' => $clockIn ? 'attendance/'.Str::uuid().'.jpg' : null,
                    'clock_in_method' => $isUpload ? 'upload' : 'live',
                    'clock_in_distance' => $clockIn ? ($outsideRadius ? mt_rand(600, 25000) : mt_rand(5, 140)) : null,
                    'clock_in_office' => $clockIn ? 'Kantor Geely Ricklean Makassar' : null,
                    'is_outside_radius' => $outsideRadius,
                    'clock_in_note' => $isUpload
                        ? ($outsideRadius ? 'Kunjungan klien di luar kantor.' : 'Kamera browser tidak dapat diakses.')
                        : null,
                    'verification_status' => match (true) {
                        ! $isUpload => 'auto',
                        $daysAgo <= 2 => 'pending',
                        default => 'approved',
                    },
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
            [
                'title' => 'Senior Backend Engineer',
                'offered_category' => 'pkwt',
                'dept' => 'TECH',
                'quota' => 2,
                'description' => "Kami mencari Senior Backend Engineer yang berpengalaman dalam membangun dan mengelola arsitektur sistem backend skala besar.\n\nAnda akan bertanggung jawab atas pengembangan API, optimasi performa, dan kolaborasi lintas tim untuk menghasilkan produk berkualitas tinggi.",
                'requirements' => "- Minimal 3 tahun pengalaman di bidang backend development\n- Menguasai PHP (Laravel) atau Go\n- Familiar dengan database relasional (MySQL/PostgreSQL)\n- Pengalaman dengan Docker dan CI/CD\n- Kemampuan komunikasi yang baik",
            ],
            [
                'title' => 'UI/UX Designer (Freelance)',
                'offered_category' => 'mitra',
                'dept' => 'TECH',
                'quota' => 1,
                'description' => 'Posisi freelance untuk UI/UX Designer yang akan bekerja pada proyek-proyek desain interface internal dan eksternal.',
                'requirements' => "- Portofolio desain UI/UX yang kuat\n- Menguasai Figma\n- Memahami prinsip usability dan accessibility\n- Dapat bekerja secara remote",
            ],
            [
                'title' => 'Finance Staff',
                'offered_category' => 'probation',
                'dept' => 'FIN',
                'quota' => 1,
                'description' => 'Bergabunglah dengan tim keuangan kami sebagai Finance Staff untuk menangani pembukuan, laporan keuangan, dan administrasi pajak.',
                'requirements' => "- S1 Akuntansi atau Keuangan\n- Memahami standar PSAK\n- Teliti dan detail-oriented\n- Fresh graduate dipersilakan melamar",
            ],
            [
                'title' => 'Digital Marketing Specialist',
                'offered_category' => 'pkwt',
                'dept' => 'MKT',
                'quota' => 3,
                'description' => 'Kami membutuhkan Digital Marketing Specialist yang kreatif dan data-driven untuk mengelola kampanye digital perusahaan.',
                'requirements' => "- Minimal 2 tahun pengalaman di digital marketing\n- Menguasai Google Ads, Meta Ads, dan SEO\n- Familiar dengan tools analytics\n- Kemampuan copywriting yang baik",
            ],
            [
                'title' => 'Content Writer (Per Artikel)',
                'offered_category' => 'mitra',
                'dept' => 'MKT',
                'quota' => 4,
                'description' => 'Mitra content writer untuk menghasilkan artikel berkualitas tinggi. Pembayaran per artikel yang dipublikasikan.',
                'requirements' => "- Pengalaman menulis artikel web/blog\n- Paham SEO on-page\n- Bisa menulis dalam Bahasa Indonesia dan Inggris\n- Disiplin terhadap deadline",
            ],
            [
                'title' => 'Warehouse Supervisor',
                'offered_category' => 'probation',
                'dept' => 'OPS',
                'quota' => 1,
                'description' => 'Memimpin operasional gudang harian, memastikan akurasi inventaris, dan mengoordinasikan tim warehouse.',
                'requirements' => "- Minimal 2 tahun pengalaman di bidang logistik/warehouse\n- Memahami WMS (Warehouse Management System)\n- Kemampuan leadership yang baik\n- Bersedia kerja shift",
            ],
        ];

        $firstNames = ['Adit', 'Bella', 'Cahya', 'Dimas', 'Erlangga', 'Fitri', 'Galuh', 'Hana', 'Irfan', 'Jasmine'];
        $lastNames = ['Prasetyo', 'Anwar', 'Setiawan', 'Rahmawati', 'Hidayat', 'Salsabila'];

        $stages = ['applied', 'applied', 'applied', 'screening', 'screening', 'interview', 'interview', 'offering', 'hired', 'rejected'];

        $sampleNotes = [
            'Kandidat menunjukkan pemahaman teknis yang kuat.',
            'Komunikasi baik, perlu evaluasi lebih lanjut pada skill teknis.',
            'Cocok dengan budaya tim, direkomendasikan untuk tahap selanjutnya.',
            'Perlu negosiasi ulang terkait ekspektasi gaji.',
            'Portofolio sangat mengesankan.',
        ];

        foreach ($vacancies as $index => $definition) {
            $vacancy = JobVacancy::create([
                'department_id' => $this->departments[$definition['dept']]->id,
                'title' => $definition['title'],
                'offered_category' => $definition['offered_category'],
                'description' => $definition['description'],
                'requirements' => $definition['requirements'],
                'location' => $this->departments[$definition['dept']]->location,
                'quota' => $definition['quota'],
                'status' => 'open',
                'published_at' => CarbonImmutable::now()->subDays(mt_rand(5, 60)),
            ]);

            foreach (range(1, mt_rand(6, 18)) as $n) {
                $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
                $stage = $stages[array_rand($stages)];

                // Build stage history for non-applied stages.
                $stageHistory = [];
                $orderedStages = ['applied', 'screening', 'interview', 'offering', 'hired'];
                $stageIndex = array_search($stage, $orderedStages);
                if ($stageIndex === false && $stage === 'rejected') {
                    $rejectFrom = $orderedStages[mt_rand(0, 2)];
                    $stageHistory[] = [
                        'from' => $rejectFrom,
                        'to' => 'rejected',
                        'changed_by' => 'Syaafiudin M',
                        'changed_at' => now()->subDays(mt_rand(1, 20))->toIso8601String(),
                    ];
                } elseif ($stageIndex !== false && $stageIndex > 0) {
                    for ($i = 0; $i < $stageIndex; $i++) {
                        $stageHistory[] = [
                            'from' => $orderedStages[$i],
                            'to' => $orderedStages[$i + 1],
                            'changed_by' => 'Syaafiudin M',
                            'changed_at' => now()->subDays(mt_rand(1, 30 - $i * 5))->toIso8601String(),
                        ];
                    }
                }

                // Some candidates get notes.
                $notes = [];
                if (in_array($stage, ['interview', 'offering', 'hired']) && mt_rand(0, 1)) {
                    $noteCount = mt_rand(1, 2);
                    for ($j = 0; $j < $noteCount; $j++) {
                        $notes[] = [
                            'content' => $sampleNotes[array_rand($sampleNotes)],
                            'author' => 'Syaafiudin M',
                            'created_at' => now()->subDays(mt_rand(1, 15))->toIso8601String(),
                        ];
                    }
                }

                Applicant::create([
                    'job_vacancy_id' => $vacancy->id,
                    'full_name' => $name,
                    'email' => Str::slug($name, '.').$index.$n.'@mail.com',
                    'phone' => '08'.mt_rand(1000000000, 9999999999),
                    'stage' => $stage,
                    'notes' => $notes ?: null,
                    'stage_history' => $stageHistory ?: null,
                    'stage_changed_at' => count($stageHistory) > 0 ? now()->subDays(mt_rand(1, 10)) : null,
                ]);
            }
        }
    }

    /**
     * Katalog inventaris dan riwayat peminjaman.
     *
     * @param  EloquentCollection<int, Employee>  $employees
     */
    private function seedInventory($employees): void
    {
        $catalog = [
            ['AST-LP-001', 'Laptop Lenovo ThinkPad E14', 'elektronik', 'Lenovo', 'LNV-8842190', 4, 12_500_000],
            ['AST-LP-002', 'MacBook Air M2', 'elektronik', 'Apple', 'APL-2291043', 2, 18_900_000],
            ['AST-PR-001', 'Proyektor Epson EB-X06', 'elektronik', 'Epson', 'EPS-771230', 2, 6_400_000],
            ['AST-KM-001', 'Kamera Canon EOS M50', 'elektronik', 'Canon', 'CNN-553218', 1, 9_800_000],
            ['AST-MD-001', 'Modem MiFi Telkomsel', 'elektronik', 'Telkomsel', null, 6, 850_000],
            ['AST-KD-001', 'Motor Operasional Honda Vario', 'kendaraan', 'Honda', 'DD-1234-AB', 2, 21_500_000],
            ['AST-KD-002', 'Mobil Operasional Toyota Avanza', 'kendaraan', 'Toyota', 'DD-9087-CD', 1, 235_000_000],
            ['AST-PK-001', 'Toolkit Teknisi Lengkap', 'perkakas', 'Tekiro', null, 3, 1_750_000],
            ['AST-FN-001', 'Kursi Lipat Acara', 'furnitur', null, null, 40, 185_000],
            ['AST-DK-001', 'Map Dokumen Legal Perusahaan', 'dokumen', null, null, 5, null],
        ];

        $items = [];

        foreach ($catalog as [$code, $name, $category, $brand, $serial, $quantity, $price]) {
            $items[$code] = InventoryItem::create([
                'code' => $code,
                'name' => $name,
                'category' => $category,
                'brand' => $brand,
                'serial_number' => $serial,
                'quantity' => $quantity,
                'condition' => 'good',
                'status' => 'active',
                'location' => $category === 'kendaraan' ? 'Parkir kantor' : 'Gudang lantai 2',
                'purchase_price' => $price,
                'purchase_date' => CarbonImmutable::now()->subMonths(mt_rand(4, 36))->toDateString(),
            ]);
        }

        $borrowers = $employees->where('status', 'active')->values();

        if ($borrowers->isEmpty()) {
            return;
        }

        $hr = User::where('role', 'super_admin')->first();
        $today = CarbonImmutable::today();

        // Campuran status agar seluruh kolom konsol HR terisi: pengajuan
        // yang menunggu keputusan, pinjaman berjalan, telat, dan selesai.
        $plan = [
            ['AST-LP-001', 'borrowed', -20, 14],
            ['AST-LP-001', 'returned', -60, -30],
            ['AST-KD-001', 'borrowed', -9, -3],   // telat, jatuh tempo lewat
            ['AST-PR-001', 'requested', 0, 7],
            ['AST-MD-001', 'requested', 0, 21],
            ['AST-KM-001', 'approved', -1, 10],
            ['AST-FN-001', 'returned', -45, -38],
            ['AST-PK-001', 'borrowed', -5, 20],
            ['AST-LP-002', 'rejected', -12, 5],
            ['AST-KD-002', 'returned', -25, -18],
        ];

        foreach ($plan as $index => [$code, $status, $createdOffset, $dueOffset]) {
            $item = $items[$code];
            $employee = $borrowers[$index % $borrowers->count()];
            $createdAt = $today->addDays($createdOffset);

            $loan = InventoryLoan::create([
                'inventory_item_id' => $item->id,
                'employee_id' => $employee->id,
                'quantity' => $code === 'AST-FN-001' ? 12 : 1,
                'status' => $status,
                'purpose' => match ($item->category) {
                    'kendaraan' => 'Kunjungan klien ke luar kota.',
                    'furnitur' => 'Persiapan town hall karyawan.',
                    'dokumen' => 'Pengurusan perizinan di notaris.',
                    default => 'Kebutuhan operasional harian.',
                },
                'due_date' => $today->addDays($dueOffset)->toDateString(),
                'handed_over_at' => in_array($status, ['borrowed', 'returned'], true) ? $createdAt->setTime(9, 30) : null,
                'returned_at' => $status === 'returned' ? $today->addDays($dueOffset)->setTime(16, 0) : null,
                'condition_out' => in_array($status, ['borrowed', 'returned'], true) ? 'good' : null,
                'condition_in' => $status === 'returned' ? (mt_rand(1, 4) === 1 ? 'minor' : 'good') : null,
                'decision_note' => in_array($status, ['approved', 'borrowed', 'returned'], true)
                    ? 'Disetujui, harap dikembalikan tepat waktu.'
                    : ($status === 'rejected' ? 'Unit sedang dialokasikan untuk tim lain.' : null),
                'decided_by' => $status === 'requested' ? null : $hr?->id,
                'decided_at' => $status === 'requested' ? null : $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $loan->save();
        }
    }
}
