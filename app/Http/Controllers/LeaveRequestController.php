<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\ExportService;
use App\Services\LeavePolicyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function __construct(private LeavePolicyService $policy) {}

    /**
     * Daftar pengajuan untuk HR & Manager (approval workflow).
     */
    public function index(Request $request): Response
    {
        $requests = $this->filtered($request)
            ->with(['employee.department', 'employee.employmentType'])
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LeaveRequest $leave) => $this->present($leave));

        return Inertia::render('Leaves/Index', [
            'requests' => $requests,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'leave_type' => $request->string('leave_type')->toString() ?: null,
                'department_id' => $request->integer('department_id') ?: null,
                'search' => $request->string('search')->toString() ?: null,
            ],
            'options' => [
                'statuses' => ['pending', 'approved', 'rejected'],
                'leaveTypes' => LeavePolicyService::LEAVE_TYPES,
                'departments' => Department::orderBy('name')->get(['id', 'name'])->all(),
            ],
            'stats' => [
                'pending' => LeaveRequest::where('status', 'pending')->count(),
                'approved' => LeaveRequest::where('status', 'approved')->count(),
                'rejected' => LeaveRequest::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * Portal cuti mandiri.
     */
    public function mine(Request $request): Response
    {
        $employee = $this->currentEmployee($request);
        $balance = $this->policy->balance($employee);

        return Inertia::render('Leaves/Mine', [
            'employee' => [
                'name' => $employee->full_name,
                'type' => $employee->employmentType?->name,
                'category' => $employee->employmentType?->category,
            ],
            'policy' => [
                // Sumber pesan yang sama dipakai server saat menolak pengajuan.
                'isLeaveEligible' => $employee->isLeaveEligible(),
                'blockedReason' => $this->policy->blockedReason($employee),
                'quota' => $balance['quota'],
                'used' => $balance['used'],
                'remaining' => $balance['remaining'],
            ],
            'requests' => $employee->leaveRequests()
                ->orderByDesc('start_date')
                ->limit(20)
                ->get()
                ->map(fn (LeaveRequest $leave) => $this->present($leave))
                ->all(),
            'options' => [
                'leaveTypes' => LeavePolicyService::LEAVE_TYPES,
                // Entitas tanpa hak cuti tahunan tetap boleh izin sakit / tanpa gaji.
                'allowedTypes' => $this->policy->allowedTypes($employee),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'leave_type' => ['required', Rule::in(LeavePolicyService::LEAVE_TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Rule engine §5.2 — pengajuan yang tidak berhak ditolak di server,
        // bukan sekadar disembunyikan di UI.
        $rejection = $this->policy->validateRequest($employee, $data['leave_type']);

        if ($rejection !== null) {
            abort(403, $rejection);
        }

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $days = $start->diffInDays($end) + 1;

        if ($data['leave_type'] === 'annual') {
            $balance = $this->policy->balance($employee);

            if ($days > $balance['remaining']) {
                return back()->with('error', sprintf(
                    'Sisa kuota cuti tahunan %d hari, pengajuan %d hari melebihi kuota.',
                    $balance['remaining'],
                    $days,
                ));
            }
        }

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $start,
            'end_date' => $end,
            'total_days' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan terkirim dan menunggu persetujuan atasan.');
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $leaveRequest->update([
            'status' => $data['status'],
            'approved_by' => $request->user()?->employee?->id,
        ]);

        $label = $data['status'] === 'approved' ? 'disetujui' : 'ditolak';

        return back()->with('success', "Pengajuan {$leaveRequest->employee?->full_name} {$label}.");
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if(
            $leaveRequest->employee_id !== $employee->id || $leaveRequest->status !== 'pending',
            403,
            'Hanya pengajuan Anda yang masih berstatus pending yang dapat dibatalkan.',
        );

        $leaveRequest->delete();

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    /**
     * Rekap cuti & izin.
     */
    public function export(Request $request, ExportService $exporter)
    {
        $rows = $this->filtered($request)
            ->with(['employee.department', 'employee.employmentType'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                $leave->employee?->nik,
                $leave->employee?->full_name,
                $leave->employee?->department?->name,
                $leave->employee?->employmentType?->name,
                LeavePolicyService::LEAVE_LABELS[$leave->leave_type] ?? $leave->leave_type,
                $leave->start_date->format('d/m/Y'),
                $leave->end_date->format('d/m/Y'),
                $leave->total_days,
                ucfirst($leave->status),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Rekap Cuti dan Izin',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Rekap Cuti & Izin',
            headings: ['NIK', 'Nama', 'Divisi', 'Entitas Kerja', 'Jenis', 'Mulai', 'Selesai', 'Total Hari', 'Status'],
            rows: $rows,
            filters: ['status' => $request->string('status')->toString() ?: 'semua'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'employee' => $leave->employee?->full_name,
            'nik' => $leave->employee?->nik,
            'department' => $leave->employee?->department?->name,
            'type' => $leave->leave_type,
            'typeLabel' => LeavePolicyService::LEAVE_LABELS[$leave->leave_type] ?? $leave->leave_type,
            'startDate' => $leave->start_date->translatedFormat('d M Y'),
            'endDate' => $leave->end_date->translatedFormat('d M Y'),
            'totalDays' => $leave->total_days,
            'reason' => $leave->reason,
            'status' => $leave->status,
        ];
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee()->with('employmentType')->first();

        abort_if(! $employee, 403, 'Akun Anda belum tertaut ke data tenaga kerja.');

        return $employee;
    }

    private function filtered(Request $request): Builder
    {
        $user = $request->user();

        return LeaveRequest::query()
            // Manager dibatasi pada divisinya sendiri.
            ->when(
                $user && ! $user->isSuperAdmin(),
                fn (Builder $query) => $query->whereHas(
                    'employee',
                    fn (Builder $inner) => $inner->where('department_id', $user->employee?->department_id),
                ),
            )
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('leave_type')->toString(), fn (Builder $query, string $type) => $query->where('leave_type', $type))
            ->when($request->integer('department_id'), fn (Builder $query, int $id) => $query->whereHas('employee', fn (Builder $inner) => $inner->where('department_id', $id)))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")));
    }
}
