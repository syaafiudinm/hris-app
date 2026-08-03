<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\MitraPayrollSchema;
use App\Models\Payroll;
use App\Models\SalesRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan periode penggajian untuk seluruh entitas kerja aktif.
 *
 * Karyawan (PKWT/Probation) memakai PayrollCalculator::calculateEmployee —
 * BPJS otomatis nol bila entitasnya tidak berhak. Mitra dibaca dari
 * mitra_payroll_schemas dengan kuantitas ditarik dari timesheet.
 */
class PayrollRunService
{
    public function __construct(private PayrollCalculator $calculator) {}

    /**
     * @return array{created: int, updated: int, skipped: int, total: float}
     */
    public function run(int $year, int $month, bool $overwritePaid = false): array
    {
        $period = CarbonImmutable::create($year, $month, 1);
        $from = $period->startOfMonth()->toDateString();
        $to = $period->endOfMonth()->toDateString();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $total = 0.0;

        $employees = Employee::active()
            ->with(['employmentType', 'mitraPayrollSchema'])
            ->get();

        DB::transaction(function () use ($employees, $year, $month, $from, $to, $overwritePaid, &$created, &$updated, &$skipped, &$total) {
            foreach ($employees as $employee) {
                if ($employee->contract_start && $employee->contract_start->gt($to)) {
                    $skipped++;

                    continue;
                }

                $existing = Payroll::where('employee_id', $employee->id)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->first();

                // Slip yang sudah dibayar tidak ditimpa kecuali diminta eksplisit.
                if ($existing && $existing->status === 'paid' && ! $overwritePaid) {
                    $skipped++;

                    continue;
                }

                $slips = $employee->isMitra()
                    ? $this->calculateMitraFromTimesheet($employee, $from, $to)
                    : [$this->calculateEmployeeWithOvertime($employee, $from, $to)];

                if ($slips === null || $slips === []) {
                    $skipped++;

                    continue;
                }

                foreach ($slips as $amounts) {
                    $slipType = $amounts['slip_type'] ?? 'salary';

                    $payload = array_merge($amounts, [
                        'employee_id' => $employee->id,
                        'period_year' => $year,
                        'period_month' => $month,
                        'slip_type' => $slipType,
                        'status' => 'draft',
                    ]);

                    $slip = Payroll::where('employee_id', $employee->id)
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->where('slip_type', $slipType)
                        ->first();

                    if ($slip) {
                        $slip->update($payload);
                        $updated++;
                    } else {
                        Payroll::create($payload);
                        $created++;
                    }

                    $total += $amounts['net_payout'];
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => round($total, 2),
        ];
    }

    /**
     * @return array<string, float|string>|null
     */
    private function calculateEmployeeWithOvertime(Employee $employee, string $from, string $to): ?array
    {
        $basic = (float) $employee->basic_salary;

        // Tunjangan tetap 10% dari gaji pokok sebagai default kebijakan.
        $allowance = $basic * 0.1;

        // Lembur: menit kerja melebihi 8 jam per hari hadir.
        // CASE dipakai alih-alih GREATEST() yang hanya ada di MySQL, supaya
        // mesin payroll dapat diuji pada SQLite.
        $overtimeMinutes = (int) Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['present', 'late'])
            ->sum(DB::raw('CASE WHEN work_minutes > 480 THEN work_minutes - 480 ELSE 0 END'));

        $hourlyRate = $basic > 0 ? $basic / 173 : 0;
        $overtime = round(($overtimeMinutes / 60) * $hourlyRate * 1.5, 2);

        $amounts = $this->calculator->calculateEmployee($employee, $basic, $allowance, $overtime);

        // Rincian komponen disimpan agar slip mencetak angka yang sama persis
        // dengan yang dihitung, tanpa menghitung ulang di template.
        $amounts['details'] = [
            'allowanceRate' => 10,
            'overtimeMinutes' => $overtimeMinutes,
            'overtimeHours' => round($overtimeMinutes / 60, 2),
            'hourlyRate' => round($hourlyRate, 2),
            'bpjs' => $employee->isBpjsEligible()
                ? $this->calculator->bpjsBreakdown($basic)
                : null,
        ];

        return $amounts;
    }

    /**
     * Mitra dapat menghasilkan lebih dari satu slip dalam satu periode —
     * skema penjualan memisahkan slip gaji dari slip insentif.
     *
     * @return list<array<string, mixed>>|null
     */
    private function calculateMitraFromTimesheet(Employee $employee, string $from, string $to): ?array
    {
        $schema = $employee->mitraPayrollSchema;

        if (! $schema) {
            return null;
        }

        $slips = $schema->schema_type === 'sales'
            ? $this->calculateSalesMitra($employee, $schema, $from, $to)
            : [$this->calculateStandardMitra($employee, $schema, $from, $to)];

        // Iuran BPJS mitra juga ditanggung perusahaan. Dasar upahnya diatur
        // pada skema karena penghasilan mitra bersifat variabel.
        $components = $schema->components ?? [];
        $bpjsBase = (float) ($components['bpjs_wage_base'] ?? 0);

        return array_map(function (array $amounts) use ($employee, $bpjsBase) {
            // Iuran dibebankan sekali saja, yaitu pada slip gaji.
            $bpjsBreakdown = null;

            if (
                $employee->isBpjsEligible()
                && $bpjsBase > 0
                && ($amounts['slip_type'] ?? 'salary') === 'salary'
            ) {
                $bpjsBreakdown = $this->calculator->bpjsBreakdown($bpjsBase);
                $amounts['bpjs_company_contribution'] = $bpjsBreakdown['grandTotal'];
            }

            // Rincian skema penjualan disimpan pada kolom details slip.
            if (isset($amounts['sales_breakdown'])) {
                $amounts['details'] = $amounts['sales_breakdown'];
                unset($amounts['sales_breakdown']);
            }

            if ($bpjsBreakdown) {
                $amounts['details'] = ($amounts['details'] ?? []) + ['bpjs' => $bpjsBreakdown];
            }

            return $amounts;
        }, $slips);
    }

    /**
     * Skema penjualan menerbitkan dua slip:
     *   1. Slip gaji     — uang makan, atau bonus tier bila target tercapai.
     *   2. Slip insentif — insentif per unit terjual, dipotong pajak.
     *
     * Slip insentif hanya terbit bila ada penjualan pada periode itu.
     *
     * @return list<array<string, mixed>>
     */
    private function calculateSalesMitra(
        Employee $employee,
        MitraPayrollSchema $schema,
        string $from,
        string $to,
    ): array {
        [$year, $month] = [(int) substr($from, 0, 4), (int) substr($from, 5, 2)];
        $config = $schema->components ?? [];

        $sales = SalesRecord::with('product')
            ->where('employee_id', $employee->id)
            ->forPeriod($year, $month)
            ->get()
            ->map(fn (SalesRecord $record) => [
                'product' => $record->product?->name ?? '-',
                'quantity' => $record->quantity,
                'incentive' => (float) ($record->product?->incentive_amount ?? 0),
            ])
            ->all();

        $totalUnits = array_sum(array_column($sales, 'quantity'));

        $presentDays = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['present', 'late'])
            ->count();

        $slips = [
            $this->calculator->calculateSalesSalary($totalUnits, $presentDays, $config),
        ];

        if ($totalUnits > 0) {
            $slips[] = $this->calculator->calculateSalesIncentive($sales, $config);
        }

        return $slips;
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateStandardMitra(
        Employee $employee,
        MitraPayrollSchema $schema,
        string $from,
        string $to,
    ): array {
        $quantity = match ($schema->schema_type) {
            'hourly' => round((int) Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$from, $to])
                ->sum('work_minutes') / 60, 2),
            'daily' => (float) Attendance::where('employee_id', $employee->id)
                ->whereBetween('date', [$from, $to])
                ->whereIn('status', ['present', 'late'])
                ->count(),
            // Fixed project, milestone, dan unit tidak punya sumber otomatis —
            // dibayar penuh satu kali dan disesuaikan manual oleh HR bila perlu.
            default => 1.0,
        };

        $components = $schema->components ?? [];
        $bonus = (float) ($components['transport_allowance'] ?? 0);

        return $this->calculator->calculateMitra($schema, $quantity, $bonus);
    }
}
