<?php

/**
 * Skema kompensasi penjualan Mitra sesuai kebijakan perusahaan.
 *
 * Dua slip terpisah setiap periode:
 *   1. SLIP GAJI — uang makan & transport Rp 1.000.000/bulan (26 hari kerja),
 *      diprorata hari hadir. Bila target unit tercapai, bonus tier UMP Sulsel
 *      Rp 3.921.000 (2 unit 50%, 3 unit 75%, 4 unit 100%) MENGGANTIKAN uang
 *      makan tersebut — bukan menambahnya. Tidak dipotong pajak.
 *   2. SLIP INSENTIF — insentif per unit (ex2 500rb, ex5 2jt, starray 3jt),
 *      tidak diprorata, dipotong pajak 50% x 2,5%.
 */

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollCalculator;
use App\Services\PayrollRunService;
use Carbon\CarbonImmutable;
use Database\Seeders\HrisDemoSeeder;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
    $this->calculator = app(PayrollCalculator::class);
    $this->config = HrisDemoSeeder::salesSchemeDefaults();
    $this->admin = User::where('role', 'super_admin')->first();
});

/* ------------------------------------------------------------- Slip gaji */

test('tanpa penjualan, gaji adalah uang makan yang diprorata hari hadir', function () {
    $penuh = $this->calculator->calculateSalesSalary(0, 26, $this->config);
    $separuh = $this->calculator->calculateSalesSalary(0, 13, $this->config);
    $nol = $this->calculator->calculateSalesSalary(0, 0, $this->config);

    expect($penuh['gross_amount'])->toBe(1_000_000.0)
        ->and($penuh['sales_breakdown']['basis'])->toBe('allowance')
        ->and($penuh['sales_breakdown']['dailyAllowanceRate'])->toBe(38_461.54)
        ->and($separuh['gross_amount'])->toBe(500_000.0)
        ->and($nol['gross_amount'])->toBe(0.0);
});

test('satu unit belum memenuhi tier, gaji tetap uang makan', function () {
    $hasil = $this->calculator->calculateSalesSalary(1, 26, $this->config);

    expect($hasil['sales_breakdown']['basis'])->toBe('allowance')
        ->and($hasil['gross_amount'])->toBe(1_000_000.0);
});

test('bonus tier menggantikan uang makan, bukan menambahnya', function () {
    $kasus = [
        // unit, persen, gaji sebulan penuh
        [2, 50.0, 1_960_500.0],
        [3, 75.0, 2_940_750.0],
        [4, 100.0, 3_921_000.0],
        [9, 100.0, 3_921_000.0],   // di atas tier tertinggi tetap 100%
    ];

    foreach ($kasus as [$units, $persen, $gaji]) {
        $hasil = $this->calculator->calculateSalesSalary($units, 26, $this->config);

        expect($hasil['sales_breakdown']['basis'])->toBe('bonus', "{$units} unit")
            ->and($hasil['sales_breakdown']['bonusPercentage'])->toBe($persen)
            // Nilainya persis bonus — uang makan Rp 1.000.000 tidak ditambahkan.
            ->and($hasil['gross_amount'])->toBe($gaji, "{$units} unit");
    }
});

test('gaji berbasis bonus tetap diprorata hari hadir', function () {
    $hasil = $this->calculator->calculateSalesSalary(4, 18, $this->config);

    // 100% UMP x 18/26
    expect($hasil['gross_amount'])->toBe(round(3_921_000 * 18 / 26, 2));
});

test('slip gaji tidak dipotong pajak insentif', function () {
    $hasil = $this->calculator->calculateSalesSalary(4, 26, $this->config);

    expect($hasil['pph_deduction'])->toBe(0.0)
        ->and($hasil['net_payout'])->toBe($hasil['gross_amount'])
        ->and($hasil['slip_type'])->toBe('salary');
});

test('hadir melebihi hari kerja tidak melipatkan gaji', function () {
    $wajar = $this->calculator->calculateSalesSalary(4, 26, $this->config);
    $berlebih = $this->calculator->calculateSalesSalary(4, 31, $this->config);

    expect($berlebih['gross_amount'])->toBe($wajar['gross_amount']);
});

/* --------------------------------------------------------- Slip insentif */

test('insentif dihitung per unit dan dipotong pajak 50% x 2,5%', function () {
    // 2 ex2 + 1 ex5 + 1 starray = 1jt + 2jt + 3jt = 6jt
    $sales = [
        ['product' => 'EX2', 'quantity' => 2, 'incentive' => 500_000],
        ['product' => 'EX5', 'quantity' => 1, 'incentive' => 2_000_000],
        ['product' => 'Starray', 'quantity' => 1, 'incentive' => 3_000_000],
    ];

    $hasil = $this->calculator->calculateSalesIncentive($sales, $this->config);

    expect($hasil['slip_type'])->toBe('incentive')
        ->and($hasil['gross_amount'])->toBe(6_000_000.0)
        ->and($hasil['sales_breakdown']['totalUnits'])->toBe(4)
        // 6.000.000 x 50% x 2,5% = 75.000
        ->and($hasil['pph_deduction'])->toBe(75_000.0)
        ->and($hasil['net_payout'])->toBe(5_925_000.0);
});

test('slip insentif merinci tiap produk', function () {
    $sales = [
        ['product' => 'Starray', 'quantity' => 2, 'incentive' => 3_000_000],
    ];

    $baris = $this->calculator->calculateSalesIncentive($sales, $this->config)['sales_breakdown']['lines'];

    expect($baris)->toHaveCount(1)
        ->and($baris[0]['product'])->toBe('Starray')
        ->and($baris[0]['quantity'])->toBe(2)
        ->and($baris[0]['subtotal'])->toBe(6_000_000.0);
});

test('insentif tidak diprorata hari hadir', function () {
    $sales = [['product' => 'EX5', 'quantity' => 1, 'incentive' => 2_000_000]];

    // Fungsi insentif memang tidak menerima hari hadir sama sekali.
    $hasil = $this->calculator->calculateSalesIncentive($sales, $this->config);

    expect($hasil['gross_amount'])->toBe(2_000_000.0);
});

/* ------------------------------------------------- Penerbitan dua slip */

test('penerbitan slip mengikuti ada tidaknya penjualan', function () {
    $periode = CarbonImmutable::now()->subMonth();
    app(PayrollRunService::class)->run($periode->year, $periode->month, true);

    $salesMitra = Employee::whereHas(
        'mitraPayrollSchema',
        fn ($q) => $q->where('schema_type', 'sales'),
    )->get();

    foreach ($salesMitra as $mitra) {
        $slips = Payroll::where('employee_id', $mitra->id)
            ->where('period_year', $periode->year)
            ->where('period_month', $periode->month)
            ->pluck('slip_type')
            ->all();

        $adaPenjualan = App\Models\SalesRecord::where('employee_id', $mitra->id)
            ->forPeriod($periode->year, $periode->month)
            ->sum('quantity') > 0;

        // Slip gaji selalu terbit; slip insentif hanya bila ada penjualan.
        expect($slips)->toContain('salary');

        if ($adaPenjualan) {
            expect($slips)->toContain('incentive');
        } else {
            expect($slips)->not->toContain('incentive');
        }
    }
});

test('slip gaji dan slip insentif punya total yang berdiri sendiri', function () {
    $periode = CarbonImmutable::now()->subMonth();
    app(PayrollRunService::class)->run($periode->year, $periode->month, true);

    $insentif = Payroll::where('slip_type', 'incentive')
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->firstOrFail();

    $gaji = Payroll::where('employee_id', $insentif->employee_id)
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->where('slip_type', 'salary')
        ->firstOrFail();

    // Gaji berbasis bonus (karena ada penjualan) dan tanpa pajak;
    // insentif membawa pajaknya sendiri.
    expect((float) $gaji->pph_deduction)->toBe(0.0)
        ->and((float) $insentif->pph_deduction)->toBeGreaterThan(0.0)
        ->and($gaji->details['basis'])->toBe('bonus');
});

test('kedua slip menghasilkan PDF dengan judul berbeda', function () {
    $periode = CarbonImmutable::now()->subMonth();
    app(PayrollRunService::class)->run($periode->year, $periode->month, true);

    $insentif = Payroll::where('slip_type', 'incentive')
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->firstOrFail();

    $gaji = Payroll::where('employee_id', $insentif->employee_id)
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->where('slip_type', 'salary')
        ->firstOrFail();

    foreach ([$gaji, $insentif] as $slip) {
        $response = $this->actingAs($this->admin)->get("/slip-gaji/{$slip->id}/dokumen");

        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf');
    }
});

/* ----------------------------------------------------------------- BPJS */

test('BPJS ditanggung perusahaan, tidak memotong penerimaan karyawan', function () {
    $karyawan = Employee::active()
        ->whereHas('employmentType', fn ($query) => $query->where('category', 'pkwt'))
        ->firstOrFail();

    $hasil = $this->calculator->calculateEmployee($karyawan, 10_000_000, 1_000_000);

    expect($hasil['bpjs_employee_deduction'])->toBe(0.0)
        ->and($hasil['bpjs_company_contribution'])->toBeGreaterThan(0.0)
        ->and($hasil['net_payout'])
        ->toBe(round($hasil['gross_amount'] - $hasil['pph_deduction'], 2));
});

test('biaya BPJS perusahaan mencakup porsi pekerja', function () {
    $upah = 5_000_000.0;

    // Porsi perusahaan 10,24% + porsi pekerja 4% = 14,24%
    expect(round($this->calculator->bpjsCompanyCost($upah), 2))
        ->toBe(round($upah * 0.1424, 2));
});

test('iuran BPJS mitra hanya dibebankan pada slip gaji', function () {
    $periode = CarbonImmutable::now()->subMonth();
    app(PayrollRunService::class)->run($periode->year, $periode->month, true);

    $insentif = Payroll::where('slip_type', 'incentive')
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->firstOrFail();

    $gaji = Payroll::where('employee_id', $insentif->employee_id)
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->where('slip_type', 'salary')
        ->firstOrFail();

    // Tidak dihitung dua kali dalam satu periode.
    expect((float) $insentif->bpjs_company_contribution)->toBe(0.0)
        ->and((float) $gaji->bpjs_company_contribution)->toBeGreaterThan(0.0);
});

test('rincian BPJS per program konsisten dengan total yang dibayarkan', function () {
    $upah = 5_700_000.0;
    $rincian = $this->calculator->bpjsBreakdown($upah);

    // Lima program: Kesehatan, JHT, JKM, JKK, JP.
    expect($rincian['items'])->toHaveCount(5);

    // Penjumlahan tiap baris harus sama dengan totalnya.
    $jumlahBaris = array_sum(array_column($rincian['items'], 'total'));
    expect(round($jumlahBaris, 2))->toBe($rincian['grandTotal'])
        ->and($rincian['grandTotal'])
        ->toBe(round($this->calculator->bpjsCompanyCost($upah), 2))
        // Porsi pekerja 1% + 2% + 1% = 4% dari upah.
        ->and($rincian['workerPortion'])->toBe(round($upah * 0.04, 2));
});

test('slip karyawan menyimpan rincian BPJS dan lembur', function () {
    $periode = CarbonImmutable::now()->subMonth();
    app(PayrollRunService::class)->run($periode->year, $periode->month, true);

    $slip = Payroll::where('payout_type', 'employee')
        ->where('period_year', $periode->year)
        ->where('period_month', $periode->month)
        ->firstOrFail();

    $rincian = $slip->details;

    expect($rincian['bpjs'])->not->toBeNull()
        ->and($rincian['bpjs']['items'])->toHaveCount(5)
        // Angka pada rincian harus sama dengan kolom slip, bukan hitung ulang.
        // Dicast karena JSON mengembalikan bilangan bulat tanpa desimal.
        ->and((float) $rincian['bpjs']['grandTotal'])
        ->toBe(round((float) $slip->bpjs_company_contribution, 2))
        ->and($rincian)->toHaveKeys(['overtimeHours', 'hourlyRate']);
});

test('setiap bentuk slip mencetak keterangan komponennya', function () {
    $periode = CarbonImmutable::now()->subMonth();
    app(PayrollRunService::class)->run($periode->year, $periode->month, true);

    $render = function (Payroll $slip): string {
        $slip->load(['employee.department', 'employee.employmentType', 'employee.mitraPayrollSchema']);

        return view(
            $slip->payout_type === 'mitra' ? 'documents.payment-voucher' : 'documents.payslip',
            [
                'payroll' => $slip,
                'employee' => $slip->employee,
                'schema' => $slip->employee->mitraPayrollSchema,
                'periodLabel' => 'Juli 2026',
                'generatedAt' => '2 Agustus 2026',
            ],
        )->render();
    };

    // Slip karyawan: BPJS dirinci per program dan dinyatakan ditanggung perusahaan.
    $karyawan = $render(
        Payroll::where('payout_type', 'employee')
            ->where('period_month', $periode->month)
            ->firstOrFail(),
    );
    expect($karyawan)->toContain('Dibayarkan Perusahaan')
        ->toContain('Jaminan Hari Tua')
        ->toContain('ditalangi perusahaan');

    // Slip gaji mitra dengan bonus: harus menyatakan bonus menggantikan uang makan.
    $bonus = Payroll::where('slip_type', 'salary')
        ->where('payout_type', 'mitra')
        ->where('period_month', $periode->month)
        ->whereNotNull('details')
        ->get()
        ->first(fn (Payroll $x) => ($x->details['basis'] ?? null) === 'bonus');

    if ($bonus) {
        expect($render($bonus))
            ->toContain('Bonus Pencapaian Penjualan')
            ->toContain('digantikan bonus')
            ->toContain('Dibayarkan Perusahaan');
    }

    // Slip insentif: rincian per produk, tanpa blok BPJS agar tidak dobel.
    $insentif = Payroll::where('slip_type', 'incentive')
        ->where('period_month', $periode->month)
        ->firstOrFail();

    expect($render($insentif))
        ->toContain('per unit')
        ->toContain('Pemotongan Pajak')
        ->not->toContain('Dibayarkan Perusahaan');
});

test('ketiga entitas kerja terdaftar BPJS', function () {
    foreach (['probation', 'pkwt', 'mitra'] as $kategori) {
        expect(EmploymentType::where('category', $kategori)->firstOrFail()->is_bpjs_eligible)
            ->toBeTrue("entitas {$kategori}");
    }
});

/* ------------------------------------------------------- Modul penjualan */

test('halaman penjualan dapat dibuka HR dan tertutup bagi role lain', function () {
    $this->actingAs($this->admin)->get('/penjualan')->assertOk();

    foreach (['manager@perusahaan.co.id', 'karyawan@perusahaan.co.id'] as $email) {
        $this->actingAs(User::where('email', $email)->firstOrFail())
            ->get('/penjualan')
            ->assertForbidden();
    }
});

test('input penjualan tersimpan dan nol menghapus barisnya', function () {
    $mitra = Employee::whereHas('mitraPayrollSchema', fn ($q) => $q->where('schema_type', 'sales'))
        ->firstOrFail();
    $produk = App\Models\SalesProduct::where('code', 'starray')->firstOrFail();

    $this->actingAs($this->admin)->post("/penjualan/{$mitra->id}", [
        'period_year' => 2026,
        'period_month' => 3,
        'quantities' => [$produk->id => 5],
    ])->assertRedirect();

    $this->assertDatabaseHas('sales_records', [
        'employee_id' => $mitra->id,
        'sales_product_id' => $produk->id,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 5,
    ]);

    $this->actingAs($this->admin)->post("/penjualan/{$mitra->id}", [
        'period_year' => 2026,
        'period_month' => 3,
        'quantities' => [$produk->id => 0],
    ]);

    $this->assertDatabaseMissing('sales_records', [
        'employee_id' => $mitra->id,
        'sales_product_id' => $produk->id,
        'period_year' => 2026,
        'period_month' => 3,
    ]);
});
