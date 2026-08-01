<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modul 5 — Executive Dashboard Analytics.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = CarbonImmutable::today();

        return Inertia::render('Dashboard', [
            'summary' => $this->summary($today),
            'workforceDistribution' => $this->workforceDistribution(),
            'compensationTrend' => $this->compensationTrend($today),
            'attendanceToday' => $this->attendanceToday($today),
            'expiringContracts' => $this->expiringContracts(),
            'recruitmentPipeline' => $this->recruitmentPipeline(),
            'generatedAt' => $today->translatedFormat('d F Y'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CarbonImmutable $today): array
    {
        $activeByCategory = Employee::active()
            ->join('employment_types', 'employment_types.id', '=', 'employees.employment_type_id')
            ->selectRaw('employment_types.category, count(*) as total')
            ->groupBy('employment_types.category')
            ->pluck('total', 'category');
            

        $employeeCount = ($activeByCategory['probation'] ?? 0) + ($activeByCategory['pkwt'] ?? 0);
        $mitraCount = $activeByCategory['mitra'] ?? 0;

        $currentCost = $this->monthlyCost($today->year, $today->month);
        $previousMonth = $today->subMonth();
        $previousCost = $this->monthlyCost($previousMonth->year, $previousMonth->month);

        $costDelta = $previousCost > 0
            ? round((($currentCost - $previousCost) / $previousCost) * 100, 1)
            : null;

        return [
            'totalWorkforce' => $employeeCount + $mitraCount,
            'employeeCount' => $employeeCount,
            'mitraCount' => $mitraCount,
            'probationCount' => $activeByCategory['probation'] ?? 0,
            'monthlyCost' => $currentCost,
            'monthlyCostDelta' => $costDelta,
            'periodLabel' => $today->translatedFormat('F Y'),
            'pendingLeaves' => LeaveRequest::where('status', 'pending')->count(),
            'expiringCount' => Employee::active()->expiringWithin(30)->count(),
            'fakeGpsFlags' => Attendance::where('is_fake_gps', true)
                ->where('date', '>=', $today->subDays(30))
                ->count(),
        ];
    }

    private function monthlyCost(int $year, int $month): float
    {
        return (float) Payroll::where('period_year', $year)
            ->where('period_month', $month)
            ->sum('net_payout');
    }

    /**
     * Distribusi tenaga kerja per entitas kerja — satu seri, dibaca lewat panjang bar.
     *
     * @return list<array<string, mixed>>
     */
    private function workforceDistribution(): array
    {
        return Employee::active()
            ->join('employment_types', 'employment_types.id', '=', 'employees.employment_type_id')
            ->selectRaw('employment_types.name, employment_types.code, employment_types.category, count(*) as total')
            ->groupBy('employment_types.name', 'employment_types.code', 'employment_types.category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->name,
                'code' => $row->code,
                'category' => $row->category,
                'value' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Biaya kompensasi 6 bulan terakhir, dipisah Karyawan vs Mitra.
     *
     * @return list<array<string, mixed>>
     */
    private function compensationTrend(CarbonImmutable $today): array
    {
        $start = $today->startOfMonth()->subMonths(5);

        $rows = Payroll::selectRaw('period_year, period_month, payout_type, sum(net_payout) as total')
            ->where(function ($query) use ($start) {
                $query->where('period_year', '>', $start->year)
                    ->orWhere(fn ($q) => $q->where('period_year', $start->year)
                        ->where('period_month', '>=', $start->month));
            })
            ->groupBy('period_year', 'period_month', 'payout_type')
            ->get()
            ->keyBy(fn ($row) => "{$row->period_year}-{$row->period_month}-{$row->payout_type}");

        $trend = [];

        for ($i = 0; $i < 6; $i++) {
            $period = $start->addMonths($i);
            $key = "{$period->year}-{$period->month}";

            $trend[] = [
                'label' => $period->translatedFormat('M'),
                'fullLabel' => $period->translatedFormat('F Y'),
                'employee' => (float) ($rows["{$key}-employee"]->total ?? 0),
                'mitra' => (float) ($rows["{$key}-mitra"]->total ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceToday(CarbonImmutable $today): array
    {
        $counts = Attendance::where('date', $today->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $leave = (int) ($counts['leave'] ?? 0);
        $recorded = $present + $late + $absent + $leave;

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'leave' => $leave,
            'recorded' => $recorded,
            'presentRate' => $recorded > 0 ? round((($present + $late) / $recorded) * 100, 1) : 0.0,
            'avgMitraHours' => round((float) Attendance::where('date', '>=', $today->subDays(30))
                ->join('employees', 'employees.id', '=', 'attendances.employee_id')
                ->join('employment_types', 'employment_types.id', '=', 'employees.employment_type_id')
                ->where('employment_types.category', 'mitra')
                ->avg(DB::raw('work_minutes / 60')) ?? 0, 1),
        ];
    }

    /**
     * Peringatan kontrak kadaluarsa H-30 (Probation, PKWT, dan Mitra).
     *
     * @return list<array<string, mixed>>
     */
    private function expiringContracts(): array
    {
        return Employee::active()
            ->expiringWithin(30)
            ->with(['employmentType', 'department'])
            ->orderBy('contract_end')
            ->limit(8)
            ->get()
            ->map(function (Employee $employee) {
                $days = (int) $employee->daysUntilContractEnd();

                return [
                    'id' => $employee->id,
                    'nik' => $employee->nik,
                    'name' => $employee->full_name,
                    'position' => $employee->position,
                    'department' => $employee->department?->name,
                    'type' => $employee->employmentType?->name,
                    'category' => $employee->employmentType?->category,
                    'endDate' => $employee->contract_end?->translatedFormat('d M Y'),
                    'daysLeft' => $days,
                    // H-14 dinaikkan ke severity kritis sesuai notification engine.
                    'severity' => $days <= 14 ? 'critical' : 'warning',
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function recruitmentPipeline(): array
    {
        $counts = Applicant::selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stages = ['applied', 'screening', 'interview', 'offering', 'hired'];
        $labels = [
            'applied' => 'Applied',
            'screening' => 'Screening',
            'interview' => 'Interview',
            'offering' => 'Offering',
            'hired' => 'Hired',
        ];

        $pipeline = array_map(fn (string $stage) => [
            'stage' => $stage,
            'label' => $labels[$stage],
            'value' => (int) ($counts[$stage] ?? 0),
        ], $stages);

        $total = (int) $counts->sum();
        $hired = (int) ($counts['hired'] ?? 0);

        return [
            'stages' => $pipeline,
            'totalApplicants' => $total,
            'conversionRate' => $total > 0 ? round(($hired / $total) * 100, 1) : 0.0,
        ];
    }
}
