<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
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

                $amounts = $employee->isMitra()
                    ? $this->calculateMitraFromTimesheet($employee, $from, $to)
                    : $this->calculateEmployeeWithOvertime($employee, $from, $to);

                if ($amounts === null) {
                    $skipped++;

                    continue;
                }

                $payload = array_merge($amounts, [
                    'employee_id' => $employee->id,
                    'period_year' => $year,
                    'period_month' => $month,
                    'status' => 'draft',
                ]);

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    Payroll::create($payload);
                    $created++;
                }

                $total += $amounts['net_payout'];
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
        $overtimeMinutes = (int) Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['present', 'late'])
            ->sum(DB::raw('GREATEST(work_minutes - 480, 0)'));

        $hourlyRate = $basic > 0 ? $basic / 173 : 0;
        $overtime = round(($overtimeMinutes / 60) * $hourlyRate * 1.5, 2);

        return $this->calculator->calculateEmployee($employee, $basic, $allowance, $overtime);
    }

    /**
     * @return array<string, float|string>|null
     */
    private function calculateMitraFromTimesheet(Employee $employee, string $from, string $to): ?array
    {
        $schema = $employee->mitraPayrollSchema;

        if (! $schema) {
            return null;
        }

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
