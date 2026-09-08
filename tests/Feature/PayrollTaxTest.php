<?php

/**
 * PPh 21 metode TER (PP 58/2023) dan perhitungan lembur.
 *
 * Dua bagian payroll yang sebelumnya hanya diverifikasi lewat smoke test
 * manual. Keduanya berhubungan langsung dengan uang yang diterima karyawan,
 * jadi angkanya dikunci di sini: perubahan tabel tarif atau rumus lembur
 * harus lewat test yang gagal lebih dulu, bukan diam-diam.
 *
 * Angka batas bracket berasal dari Lampiran PP 58/2023 — lihat catatan pada
 * App\Support\TerTariff.
 */

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Payroll;
use App\Services\PayrollCalculator;
use App\Services\PayrollRunService;
use App\Support\TerTariff;

beforeEach(function () {
    $this->calculator = app(PayrollCalculator::class);

    $this->pkwt = EmploymentType::create([
        'code' => 'PKWT12-TEST',
        'name' => 'PKWT 12 Bulan',
        'category' => 'pkwt',
        'duration_months' => 12,
        'is_leave_eligible' => true,
        'is_bpjs_eligible' => true,
        'annual_leave_quota' => 12,
    ]);
});

/**
 * Karyawan minimal untuk menguji kalkulator, tanpa menyeret seluruh seeder.
 */
function karyawanPajak(EmploymentType $type, string $ptkp, float $gaji = 10_000_000, ?string $nik = null): Employee
{
    return Employee::create([
        'employment_type_id' => $type->id,
        'nik' => $nik ?? 'TAX-'.str_pad((string) Employee::count() + 1, 4, '0', STR_PAD_LEFT),
        'full_name' => 'Karyawan '.$ptkp,
        'ptkp_status' => $ptkp,
        'join_date' => '2026-01-01',
        'contract_start' => '2026-01-01',
        'basic_salary' => $gaji,
        'status' => 'active',
    ]);
}

/* ------------------------------------------------ Pemetaan PTKP → TER */

test('status PTKP dipetakan ke kategori TER sesuai PP 58/2023', function () {
    expect(TerTariff::categoryFor('TK/0'))->toBe('A')
        ->and(TerTariff::categoryFor('TK/1'))->toBe('A')
        ->and(TerTariff::categoryFor('K/0'))->toBe('A')
        ->and(TerTariff::categoryFor('TK/2'))->toBe('B')
        ->and(TerTariff::categoryFor('TK/3'))->toBe('B')
        ->and(TerTariff::categoryFor('K/1'))->toBe('B')
        ->and(TerTariff::categoryFor('K/2'))->toBe('B')
        ->and(TerTariff::categoryFor('K/3'))->toBe('C');
});

test('status PTKP kosong atau tidak dikenal jatuh ke TER A', function () {
    // TER A punya PTKP terendah, jadi potongannya paling besar. Lebih potong
    // dapat direstitusi lewat SPT; kurang potong jadi utang pajak karyawan.
    expect(TerTariff::categoryFor(null))->toBe('A')
        ->and(TerTariff::categoryFor(''))->toBe('A')
        ->and(TerTariff::categoryFor('K/4'))->toBe('A');
});

/* -------------------------------------------------- Keutuhan tabel TER */

test('tabel TER naik monoton dan ditutup tarif 34 persen', function (string $kategori) {
    $brackets = TerTariff::brackets($kategori);

    expect($brackets)->not->toBeEmpty();

    $batasSebelumnya = -1;
    $tarifSebelumnya = -1.0;

    foreach ($brackets as [$batas, $tarif]) {
        // Batas dan tarif keduanya harus naik: satu baris yang turun berarti
        // ada angka salah ketik yang membuat sebagian penghasilan tidak
        // pernah terjangkau bracket-nya.
        expect($batas)->toBeGreaterThan($batasSebelumnya)
            ->and($tarif)->toBeGreaterThanOrEqual($tarifSebelumnya);

        $batasSebelumnya = $batas;
        $tarifSebelumnya = $tarif;
    }

    expect(end($brackets)[0])->toBe(PHP_INT_MAX)
        ->and(end($brackets)[1])->toBe(0.34)
        ->and($brackets[0][1])->toBe(0.0);
})->with(['A', 'B', 'C']);

/* -------------------------------------------------- Batas bawah bebas pajak */

test('penghasilan di bawah ambang tiap kategori tidak dipotong PPh', function () {
    // Ambang bebas pajak berbeda per kategori — inilah alasan status PTKP
    // harus tersimpan, bukan diasumsikan TK/0 untuk semua orang.
    expect($this->calculator->pph21Ter(5_400_000, 'TK/0'))->toBe(0.0)
        ->and($this->calculator->pph21Ter(6_200_000, 'TK/2'))->toBe(0.0)
        ->and($this->calculator->pph21Ter(6_600_000, 'K/3'))->toBe(0.0);
});

test('penghasilan sama, PTKP berbeda, potongan berbeda', function () {
    $bruto = 6_400_000.0;

    $a = $this->calculator->pph21Ter($bruto, 'TK/0');  // 6.300.000–6.750.000 → 1%
    $b = $this->calculator->pph21Ter($bruto, 'TK/2');  // masih di bawah 6.500.000 → 0,25%
    $c = $this->calculator->pph21Ter($bruto, 'K/3');   // masih di bawah 6.600.000 → 0%

    expect($a)->toBe(64_000.0)
        ->and($b)->toBe(16_000.0)
        ->and($c)->toBe(0.0);
});

/* -------------------------------------------------- Batas atas inklusif */

test('penghasilan tepat di angka batas memakai tarif bracket itu, bukan berikutnya', function () {
    // TER A: s.d. 5.650.000 → 0,25%; di atasnya 0,5%.
    expect($this->calculator->pph21Ter(5_650_000, 'TK/0'))->toBe(14_125.0)
        ->and($this->calculator->pph21Ter(5_650_001, 'TK/0'))->toBe(28_250.005);
});

test('bracket 11 juta TER A memakai 3 persen dan 3,5 persen, bukan lompat ke 4 persen', function () {
    // Regresi: tabel lama menyederhanakan dua bracket ini menjadi satu 4%,
    // sehingga karyawan pada band 11,05–11,6 juta kelebihan potong.
    // Dibandingkan dengan toleransi: 11.500.000 x 0,035 tidak representable
    // persis sebagai float biner, dan pembulatan ke rupiah baru terjadi saat
    // slip disimpan.
    expect($this->calculator->pph21Ter(11_000_000, 'TK/0'))->toBe(330_000.0)
        ->and($this->calculator->pph21Ter(11_500_000, 'TK/0'))->toEqualWithDelta(402_500.0, 0.01)
        ->and($this->calculator->pph21Ter(12_000_000, 'TK/0'))->toBe(480_000.0);
});

test('penghasilan sangat besar memakai tarif tertinggi 34 persen', function () {
    expect($this->calculator->pph21Ter(2_000_000_000, 'TK/0'))->toBe(680_000_000.0)
        ->and($this->calculator->pph21Ter(2_000_000_000, 'K/3'))->toBe(680_000_000.0);
});

/* -------------------------------------------------- Integrasi ke slip gaji */

test('potongan PPh pada slip mengikuti status PTKP karyawan', function () {
    $lajang = karyawanPajak($this->pkwt, 'TK/0', 10_000_000, 'TAX-A1');
    $berkeluarga = karyawanPajak($this->pkwt, 'K/3', 10_000_000, 'TAX-C1');

    // Bruto = pokok + tunjangan 10% = 11.000.000.
    $slipLajang = $this->calculator->calculateEmployee($lajang, 10_000_000, 1_000_000);
    $slipBerkeluarga = $this->calculator->calculateEmployee($berkeluarga, 10_000_000, 1_000_000);

    expect($slipLajang['pph_deduction'])->toBe(330_000.0)      // TER A: 11 jt → 3%
        ->and($slipBerkeluarga['pph_deduction'])->toBe(192_500.0) // TER C: 11 jt → 1,75%
        ->and($slipBerkeluarga['net_payout'])->toBeGreaterThan($slipLajang['net_payout']);
});

test('rincian PPh disimpan pada slip agar dapat dicocokkan karyawan', function () {
    $rincian = $this->calculator->pph21Breakdown(11_000_000, 'K/1');

    expect($rincian['ptkpStatus'])->toBe('K/1')
        ->and($rincian['terCategory'])->toBe('B')
        ->and($rincian['ptkpAnnual'])->toBe(63_000_000)
        ->and($rincian['ratePercent'])->toBe(2.0)
        ->and($rincian['base'])->toBe(11_000_000.0)
        ->and($rincian['amount'])->toBe(220_000.0);
});

test('karyawan tanpa status PTKP tersimpan tetap terhitung, memakai default TK/0', function () {
    $karyawan = karyawanPajak($this->pkwt, TerTariff::DEFAULT_PTKP, 10_000_000, 'TAX-D1');

    expect($karyawan->fresh()->ptkp_status)->toBe('TK/0')
        ->and($this->calculator->calculateEmployee($karyawan, 10_000_000, 1_000_000)['pph_deduction'])
        ->toBe(330_000.0);
});

/* ------------------------------------------------------------- Lembur */

test('lembur dihitung dari menit kerja di atas 8 jam, 1,5 kali tarif per jam', function () {
    $karyawan = karyawanPajak($this->pkwt, 'TK/0', 8_650_000, 'TAX-OT1');

    // Dua hari lembur: 60 menit dan 30 menit. Hari ketiga tepat 8 jam.
    Attendance::create(['employee_id' => $karyawan->id, 'date' => '2026-09-01', 'status' => 'present', 'work_minutes' => 540]);
    Attendance::create(['employee_id' => $karyawan->id, 'date' => '2026-09-02', 'status' => 'late', 'work_minutes' => 510]);
    Attendance::create(['employee_id' => $karyawan->id, 'date' => '2026-09-03', 'status' => 'present', 'work_minutes' => 480]);

    app(PayrollRunService::class)->run(2026, 9);

    $slip = Payroll::where('employee_id', $karyawan->id)->first();
    $rincian = $slip->details;

    // 8.650.000 / 173 = 50.000 per jam; 1,5 jam x 50.000 x 1,5 = 112.500.
    // details disimpan sebagai JSON, jadi 50000.0 kembali sebagai int —
    // toEqual, bukan toBe yang juga membandingkan tipe.
    expect($rincian['overtimeMinutes'])->toBe(90)
        ->and($rincian['overtimeHours'])->toBe(1.5)
        ->and($rincian['hourlyRate'])->toEqual(50_000)
        ->and((float) $slip->overtime_amount)->toBe(112_500.0);
});

test('hari yang tidak diakui hadir tidak menyumbang jam lembur', function () {
    $karyawan = karyawanPajak($this->pkwt, 'TK/0', 8_650_000, 'TAX-OT2');

    // Absensi yang ditolak HR berubah status menjadi absent; menitnya tidak
    // boleh ikut terhitung sebagai lembur.
    Attendance::create(['employee_id' => $karyawan->id, 'date' => '2026-09-01', 'status' => 'absent', 'work_minutes' => 600]);
    Attendance::create(['employee_id' => $karyawan->id, 'date' => '2026-09-02', 'status' => 'leave', 'work_minutes' => 600]);

    app(PayrollRunService::class)->run(2026, 9);

    $slip = Payroll::where('employee_id', $karyawan->id)->first();

    expect($slip->details['overtimeMinutes'])->toBe(0)
        ->and((float) $slip->overtime_amount)->toBe(0.0);
});

test('lembur menambah bruto sehingga ikut menaikkan dasar PPh', function () {
    $karyawan = karyawanPajak($this->pkwt, 'TK/0', 8_650_000, 'TAX-OT3');

    Attendance::create(['employee_id' => $karyawan->id, 'date' => '2026-09-01', 'status' => 'present', 'work_minutes' => 540]);

    app(PayrollRunService::class)->run(2026, 9);

    $slip = Payroll::where('employee_id', $karyawan->id)->first();

    // Bruto = 8.650.000 + tunjangan 865.000 + lembur (1 jam x 50.000 x 1,5).
    expect((float) $slip->gross_amount)->toBe(9_590_000.0)
        ->and($slip->details['pph']['base'])->toEqual(9_590_000)
        ->and((float) $slip->pph_deduction)
        ->toBe(round(9_590_000 * TerTariff::rateFor(9_590_000, 'TK/0'), 2));
});
