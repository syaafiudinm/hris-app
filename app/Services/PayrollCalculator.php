<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MitraPayrollSchema;

/**
 * Mesin kalkulasi kompensasi.
 *
 * Rule enforcement (Masterplan §5.2):
 *  - BPJS hanya dihitung bila employment_types.is_bpjs_eligible = true
 *    (Probation & Mitra otomatis 0).
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

        $employeeDeduction = 0.0;
        $companyContribution = 0.0;

        if ($bpjsEligible) {
            $jpBase = min($basic, self::JP_SALARY_CAP);

            $employeeDeduction =
                $basic * self::BPJS_KES_EMPLOYEE
                + $basic * self::BPJS_JHT_EMPLOYEE
                + $jpBase * self::BPJS_JP_EMPLOYEE;

            $companyContribution =
                $basic * self::BPJS_KES_COMPANY
                + $basic * self::BPJS_JHT_COMPANY
                + $basic * self::BPJS_JKM_COMPANY
                + $basic * self::BPJS_JKK_COMPANY
                + $jpBase * self::BPJS_JP_COMPANY;
        }

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
            'hourly', 'daily', 'unit' => $rate * $quantity,
            // milestone: quantity = persentase penyelesaian (0..1)
            'milestone' => $rate * $quantity,
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
            // Mitra tidak masuk kepesertaan BPJS perusahaan.
            'bpjs_employee_deduction' => 0.0,
            'bpjs_company_contribution' => 0.0,
            'pph_deduction' => round($tax, 2),
            'other_deduction' => round($penalty, 2),
            'net_payout' => round($net, 2),
        ];
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
