<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MitraPayrollSchema;

/**
 * Mesin kalkulasi kompensasi.
 *
 * Rule enforcement (Masterplan §5.2):
 *  - BPJS hanya dihitung bila employment_types.is_bpjs_eligible = true.
 *  - Iuran BPJS **ditanggung sepenuhnya perusahaan** sesuai kebijakan
 *    perusahaan pengguna: porsi pekerja tidak dipotong dari take home pay,
 *    melainkan ikut dibayarkan perusahaan.
 *  - Mitra tidak melewati skema gaji karyawan sama sekali; kalkulasi dibaca
 *    dari mitra_payroll_schemas.
 */
class PayrollCalculator
{
    // Potongan pekerja
    public const BPJS_KES_EMPLOYEE = 0.01;   // 1%
    public const BPJS_JHT_EMPLOYEE = 0.02;   // 2%
    public const BPJS_JP_EMPLOYEE = 0.01;    // 1%

    // Kontribusi perusahaan
    public const BPJS_KES_COMPANY = 0.04;    // 4%
    public const BPJS_JHT_COMPANY = 0.037;   // 3.7%
    public const BPJS_JKM_COMPANY = 0.003;   // 0.3%
    public const BPJS_JKK_COMPANY = 0.0024;  // 0.24% (risiko sangat rendah)
    public const BPJS_JP_COMPANY = 0.02;     // 2%

    // Batas upah dasar perhitungan JP (PP 45/2015, disesuaikan berkala)
    public const JP_SALARY_CAP = 10547400;

    /**
     * Gaji karyawan (PKWT & Probation).
     *
     * @return array<string, float>
     */
    public function calculateEmployee(
        Employee $employee,
        float $basic,
        float $allowance = 0,
        float $overtime = 0,
    ): array {
        $gross = $basic + $allowance + $overtime;
        $bpjsEligible = $employee->isBpjsEligible();

        $companyContribution = $bpjsEligible ? $this->bpjsCompanyCost($basic) : 0.0;

        // Porsi pekerja tetap dibayarkan perusahaan, jadi tidak mengurangi
        // penerimaan karyawan.
        $employeeDeduction = 0.0;

        $pph = $this->pph21Ter($gross);
        $net = $gross - $employeeDeduction - $pph;

        return [
            'payout_type' => 'employee',
            'basic_amount' => round($basic, 2),
            'allowance_amount' => round($allowance, 2),
            'overtime_amount' => round($overtime, 2),
            'gross_amount' => round($gross, 2),
            'bpjs_employee_deduction' => round($employeeDeduction, 2),
            'bpjs_company_contribution' => round($companyContribution, 2),
            'pph_deduction' => round($pph, 2),
            'other_deduction' => 0.0,
            'net_payout' => round($net, 2),
        ];
    }

    /**
     * Rincian iuran BPJS per program, untuk dicetak pada slip.
     *
     * Seluruhnya ditanggung perusahaan — kolom "porsi pekerja" tetap
     * ditampilkan agar pekerja tahu berapa yang seharusnya ia bayar sendiri
     * dan kini ditalangi perusahaan.
     *
     * @return array<string, mixed>
     */
    public function bpjsBreakdown(float $wage): array
    {
        $jpBase = min($wage, self::JP_SALARY_CAP);

        $programs = [
            ['label' => 'BPJS Kesehatan', 'base' => $wage, 'company' => self::BPJS_KES_COMPANY, 'worker' => self::BPJS_KES_EMPLOYEE],
            ['label' => 'BPJS TK — Jaminan Hari Tua (JHT)', 'base' => $wage, 'company' => self::BPJS_JHT_COMPANY, 'worker' => self::BPJS_JHT_EMPLOYEE],
            ['label' => 'BPJS TK — Jaminan Kematian (JKM)', 'base' => $wage, 'company' => self::BPJS_JKM_COMPANY, 'worker' => 0.0],
            ['label' => 'BPJS TK — Jaminan Kecelakaan Kerja (JKK)', 'base' => $wage, 'company' => self::BPJS_JKK_COMPANY, 'worker' => 0.0],
            ['label' => 'BPJS TK — Jaminan Pensiun (JP)', 'base' => $jpBase, 'company' => self::BPJS_JP_COMPANY, 'worker' => self::BPJS_JP_EMPLOYEE],
        ];

        $items = [];
        $companyTotal = 0.0;
        $workerTotal = 0.0;

        foreach ($programs as $program) {
            $companyAmount = $program['base'] * $program['company'];
            $workerAmount = $program['base'] * $program['worker'];

            $companyTotal += $companyAmount;
            $workerTotal += $workerAmount;

            $items[] = [
                'label' => $program['label'],
                'companyRate' => round($program['company'] * 100, 2),
                'workerRate' => round($program['worker'] * 100, 2),
                'companyAmount' => round($companyAmount, 2),
                'workerAmount' => round($workerAmount, 2),
                'total' => round($companyAmount + $workerAmount, 2),
            ];
        }

        return [
            'wageBase' => round($wage, 2),
            'jpBase' => round($jpBase, 2),
            'items' => $items,
            // Porsi pekerja yang ditalangi perusahaan.
            'workerPortion' => round($workerTotal, 2),
            'companyPortion' => round($companyTotal, 2),
            'grandTotal' => round($companyTotal + $workerTotal, 2),
        ];
    }

    /**
     * Total biaya BPJS yang ditanggung perusahaan atas suatu upah —
     * porsi perusahaan ditambah porsi pekerja yang ikut ditalangi.
     */
    public function bpjsCompanyCost(float $wage): float
    {
        $jpBase = min($wage, self::JP_SALARY_CAP);

        $companyPortion =
            $wage * self::BPJS_KES_COMPANY
            + $wage * self::BPJS_JHT_COMPANY
            + $wage * self::BPJS_JKM_COMPANY
            + $wage * self::BPJS_JKK_COMPANY
            + $jpBase * self::BPJS_JP_COMPANY;

        $workerPortion =
            $wage * self::BPJS_KES_EMPLOYEE
            + $wage * self::BPJS_JHT_EMPLOYEE
            + $jpBase * self::BPJS_JP_EMPLOYEE;

        return $companyPortion + $workerPortion;
    }

    /**
     * Pembayaran mitra berdasarkan skema custom.
     *
     * @param  float  $quantity  jam kerja / hari kerja / unit / persentase milestone
     * @return array<string, float>
     */
    public function calculateMitra(
        MitraPayrollSchema $schema,
        float $quantity = 1,
        float $bonus = 0,
        float $penalty = 0,
    ): array {
        $rate = (float) $schema->rate_per_unit;

        $base = match ($schema->schema_type) {
            'fixed_project' => $rate,
            // milestone: quantity = persentase penyelesaian (0..1)
            // Skema 'sales' punya jalurnya sendiri dan tidak sampai ke sini;
            // default menjaga agar tipe baru tidak melempar UnhandledMatchError.
            default => $rate * $quantity,
        };

        $gross = $base + $bonus;
        $tax = $gross * ((float) $schema->custom_tax_percentage / 100);
        $net = $gross - $tax - $penalty;

        return [
            'payout_type' => 'mitra',
            'basic_amount' => round($base, 2),
            'allowance_amount' => round($bonus, 2),
            'overtime_amount' => 0.0,
            'gross_amount' => round($gross, 2),
            // Iuran mitra pun ditanggung perusahaan; nilainya dihitung
            // PayrollRunService karena bergantung pada dasar upah yang dipakai.
            'bpjs_employee_deduction' => 0.0,
            'bpjs_company_contribution' => 0.0,
            'pph_deduction' => round($tax, 2),
            'other_deduction' => round($penalty, 2),
            'net_payout' => round($net, 2),
        ];
    }

    /**
     * Slip GAJI mitra berskema penjualan.
     *
     * Bonus tier bersifat **menggantikan**, bukan menambah: bila target unit
     * tercapai, nilai bonus menimpa uang makan & transport sebagai gaji
     * bulanan. Bila tidak tercapai, mitra tetap menerima uang makan.
     * Keduanya diprorata terhadap hari hadir.
     *
     * Insentif penjualan tidak ikut di sini — lihat calculateSalesIncentive().
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function calculateSalesSalary(int $totalUnits, int $presentDays, array $config): array
    {
        $workingDays = max((int) ($config['working_days'] ?? 26), 1);
        $allowance = (float) ($config['monthly_allowance'] ?? 0);
        $ump = (float) ($config['ump_reference'] ?? 0);

        // Hari hadir dibatasi agar kelebihan hadir tidak melipatkan gaji.
        $effectiveDays = min($presentDays, $workingDays);
        $proration = $effectiveDays / $workingDays;

        $bonusPercentage = $this->bonusPercentageForUnits($totalUnits, $config['bonus_tiers'] ?? []);
        $achieved = $bonusPercentage > 0;

        // Dasar gaji bulanan: bonus tier bila tercapai, selain itu uang makan.
        $monthlyBase = $achieved ? $ump * ($bonusPercentage / 100) : $allowance;
        $gross = $monthlyBase * $proration;

        return [
            'payout_type' => 'mitra',
            'slip_type' => 'salary',
            'basic_amount' => round($gross, 2),
            'allowance_amount' => 0.0,
            'overtime_amount' => 0.0,
            'gross_amount' => round($gross, 2),
            'bpjs_employee_deduction' => 0.0,
            'bpjs_company_contribution' => 0.0,
            // Pajak hanya mengenai insentif, jadi slip gaji tidak dipotong.
            'pph_deduction' => 0.0,
            'other_deduction' => 0.0,
            'net_payout' => round($gross, 2),
            'sales_breakdown' => [
                'basis' => $achieved ? 'bonus' : 'allowance',
                'presentDays' => $effectiveDays,
                'workingDays' => $workingDays,
                'totalUnits' => $totalUnits,
                'monthlyAllowance' => round($allowance, 2),
                'dailyAllowanceRate' => round($allowance / $workingDays, 2),
                'bonusPercentage' => $bonusPercentage,
                'umpReference' => round($ump, 2),
                'monthlyBase' => round($monthlyBase, 2),
                'dailyBaseRate' => round($monthlyBase / $workingDays, 2),
            ],
        ];
    }

    /**
     * Slip INSENTIF penjualan mitra — dibayarkan terpisah dari slip gaji.
     *
     * Tidak diprorata hari hadir; besarnya murni dari unit terjual.
     * Pajak 50% x 2,5% dikenakan di sini.
     *
     * @param  array<int, array{quantity: int, incentive: float}>  $sales
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function calculateSalesIncentive(array $sales, array $config): array
    {
        $taxBasePercentage = (float) ($config['incentive_tax_base_percentage'] ?? 50);
        $taxRate = (float) ($config['incentive_tax_rate'] ?? 2.5);

        $incentive = 0.0;
        $totalUnits = 0;
        $lines = [];

        foreach ($sales as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);
            $rate = (float) ($line['incentive'] ?? 0);
            $subtotal = $quantity * $rate;

            $incentive += $subtotal;
            $totalUnits += $quantity;

            $lines[] = [
                'product' => $line['product'] ?? '-',
                'quantity' => $quantity,
                'rate' => round($rate, 2),
                'subtotal' => round($subtotal, 2),
            ];
        }

        $tax = $incentive * ($taxBasePercentage / 100) * ($taxRate / 100);

        return [
            'payout_type' => 'mitra',
            'slip_type' => 'incentive',
            'basic_amount' => round($incentive, 2),
            'allowance_amount' => 0.0,
            'overtime_amount' => 0.0,
            'gross_amount' => round($incentive, 2),
            'bpjs_employee_deduction' => 0.0,
            'bpjs_company_contribution' => 0.0,
            'pph_deduction' => round($tax, 2),
            'other_deduction' => 0.0,
            'net_payout' => round($incentive - $tax, 2),
            'sales_breakdown' => [
                'totalUnits' => $totalUnits,
                'incentiveAmount' => round($incentive, 2),
                'taxBasePercentage' => $taxBasePercentage,
                'taxRate' => $taxRate,
                'taxAmount' => round($tax, 2),
                'lines' => $lines,
            ],
        ];
    }

    /**
     * Persentase bonus berdasarkan total unit terjual. Tier tertinggi yang
     * syaratnya terpenuhi yang dipakai.
     *
     * @param  array<int, array{units: int|string, percentage: int|float|string}>  $tiers
     */
    public function bonusPercentageForUnits(int $totalUnits, array $tiers): float
    {
        $percentage = 0.0;

        foreach ($tiers as $tier) {
            $requiredUnits = (int) ($tier['units'] ?? 0);

            if ($totalUnits >= $requiredUnits && $requiredUnits > 0) {
                $percentage = max($percentage, (float) ($tier['percentage'] ?? 0));
            }
        }

        return $percentage;
    }

    /**
     * PPh 21 metode TER bulanan (PP 58/2023), kategori TER A.
     * Tabel disederhanakan pada bracket yang umum dipakai payroll bulanan.
     */
    public function pph21Ter(float $monthlyGross): float
    {
        $rate = match (true) {
            $monthlyGross <= 5_400_000 => 0.0,
            $monthlyGross <= 5_650_000 => 0.0025,
            $monthlyGross <= 5_950_000 => 0.005,
            $monthlyGross <= 6_300_000 => 0.0075,
            $monthlyGross <= 6_750_000 => 0.01,
            $monthlyGross <= 7_500_000 => 0.0125,
            $monthlyGross <= 8_550_000 => 0.015,
            $monthlyGross <= 9_650_000 => 0.0175,
            $monthlyGross <= 10_050_000 => 0.02,
            $monthlyGross <= 10_350_000 => 0.0225,
            $monthlyGross <= 10_700_000 => 0.025,
            $monthlyGross <= 11_050_000 => 0.03,
            $monthlyGross <= 11_600_000 => 0.04,
            $monthlyGross <= 12_500_000 => 0.05,
            $monthlyGross <= 13_750_000 => 0.06,
            $monthlyGross <= 15_100_000 => 0.07,
            $monthlyGross <= 16_950_000 => 0.08,
            $monthlyGross <= 19_750_000 => 0.09,
            $monthlyGross <= 24_150_000 => 0.10,
            $monthlyGross <= 26_450_000 => 0.11,
            $monthlyGross <= 28_000_000 => 0.12,
            $monthlyGross <= 30_050_000 => 0.13,
            $monthlyGross <= 32_400_000 => 0.14,
            $monthlyGross <= 35_400_000 => 0.15,
            default => 0.17,
        };

        return $monthlyGross * $rate;
    }
}
