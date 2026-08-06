<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Services\AccountProvisioningService;
use App\Services\ExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Data induk karyawan & mitra.
     */
    public function index(Request $request): Response
    {
        $employees = $this->filtered($request)
            ->with(['employmentType', 'department'])
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'type' => $employee->employmentType?->name,
                'category' => $employee->employmentType?->category,
                'contractEnd' => $employee->contract_end?->translatedFormat('d M Y'),
                'daysLeft' => $employee->daysUntilContractEnd(),
                'salary' => (float) $employee->basic_salary,
                'status' => $employee->status,
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $this->filterValues($request),
            'options' => $this->options(),
            'stats' => [
                'total' => Employee::count(),
                'active' => Employee::active()->count(),
                'expiring' => Employee::active()->expiringWithin(30)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $employee = Employee::create($data);

        // Auto-provision akun login jika email tersedia.
        if ($employee->email) {
            $provisioner = app(AccountProvisioningService::class);
            $result = $provisioner->provision($employee);

            return redirect()
                ->route('employees.show', $employee)
                ->with('success', "Data {$employee->full_name} berhasil disimpan. Akun login dibuat dengan password: {$result['generated_password']}");
        }

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', "Data {$employee->full_name} berhasil disimpan.");
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['employmentType', 'department', 'mitraPayrollSchema', 'exit']);

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'type' => $employee->employmentType?->name,
                'category' => $employee->employmentType?->category,
                'joinDate' => $employee->join_date?->translatedFormat('d F Y'),
                'contractStart' => $employee->contract_start?->translatedFormat('d F Y'),
                'contractEnd' => $employee->contract_end?->translatedFormat('d F Y'),
                'daysLeft' => $employee->daysUntilContractEnd(),
                'salary' => (float) $employee->basic_salary,
                'status' => $employee->status,
                // Hak yang menempel pada entitas kerja — ditegakkan server-side.
                'isLeaveEligible' => $employee->isLeaveEligible(),
                'isBpjsEligible' => $employee->isBpjsEligible(),
                'leaveQuota' => $employee->employmentType?->annual_leave_quota ?? 0,
            ],
            'mitraSchema' => $employee->mitraPayrollSchema ? [
                'schemaType' => $employee->mitraPayrollSchema->schema_type,
                'rate' => (float) $employee->mitraPayrollSchema->rate_per_unit,
                'unitLabel' => $employee->mitraPayrollSchema->unit_label,
                'taxScheme' => $employee->mitraPayrollSchema->tax_scheme,
                'taxPercentage' => (float) $employee->mitraPayrollSchema->custom_tax_percentage,
            ] : null,
            'account' => $employee->user ? [
                'id' => $employee->user->id,
                'email' => $employee->user->email,
                'role' => $employee->user->role,
                'mustChangePassword' => (bool) $employee->user->must_change_password,
                'lastLogin' => $employee->user->updated_at?->diffForHumans(),
            ] : null,
            'exit' => $employee->exit ? [
                'id' => $employee->exit->id,
                'typeLabel' => $employee->exit->typeLabel(),
                'lastWorkingDate' => $employee->exit->last_working_date->translatedFormat('d F Y'),
                'tenure' => $employee->exit->tenure()['label'],
                'status' => $employee->exit->status,
                'paklaringNumber' => $employee->exit->paklaring_number,
            ] : null,
            'recentPayrolls' => $employee->payrolls()
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->limit(6)
                ->get()
                ->map(fn ($payroll) => [
                    'id' => $payroll->id,
                    'period' => sprintf('%02d/%d', $payroll->period_month, $payroll->period_year),
                    'gross' => (float) $payroll->gross_amount,
                    'net' => (float) $payroll->net_payout,
                ])
                ->all(),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'position' => $employee->position,
                'employment_type_id' => $employee->employment_type_id,
                'department_id' => $employee->department_id,
                'join_date' => $employee->join_date?->toDateString(),
                'contract_start' => $employee->contract_start?->toDateString(),
                'contract_end' => $employee->contract_end?->toDateString(),
                'basic_salary' => (float) $employee->basic_salary,
                'status' => $employee->status,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->validated($request, $employee));

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', 'Perubahan data berhasil disimpan.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $name = $employee->full_name;

        // Hapus akun login terkait.
        if ($employee->user_id) {
            app(AccountProvisioningService::class)->revoke($employee);
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('success', "Data {$name} telah dihapus.");
    }

    /**
     * Buatkan akun login untuk karyawan yang belum punya.
     */
    public function provisionAccount(Employee $employee): RedirectResponse
    {
        if (! $employee->email) {
            return back()->with('error', 'Karyawan harus memiliki email untuk membuat akun.');
        }

        $provisioner = app(AccountProvisioningService::class);
        $result = $provisioner->provision($employee);

        return back()->with(
            'success',
            "Akun login berhasil dibuat untuk {$employee->full_name}. Password: {$result['generated_password']}"
        );
    }

    /**
     * Reset password akun karyawan.
     */
    public function resetPassword(Employee $employee): RedirectResponse
    {
        $provisioner = app(AccountProvisioningService::class);
        $newPassword = $provisioner->resetPassword($employee);

        return back()->with(
            'success',
            "Password direset untuk {$employee->full_name}. Password baru: {$newPassword}"
        );
    }

    /**
     * Cabut akun login karyawan.
     */
    public function revokeAccount(Employee $employee): RedirectResponse
    {
        app(AccountProvisioningService::class)->revoke($employee);

        return back()->with('success', "Akun login {$employee->full_name} telah dicabut.");
    }

    /**
     * Ekspor Data Induk Karyawan & Mitra.
     */
    public function export(Request $request, ExportService $exporter)
    {
        $rows = $this->filtered($request)
            ->with(['employmentType', 'department'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->nik,
                $employee->full_name,
                $employee->email,
                $employee->phone,
                $employee->position,
                $employee->department?->name,
                $employee->employmentType?->name,
                $employee->contract_start?->format('d/m/Y'),
                $employee->contract_end?->format('d/m/Y'),
                number_format((float) $employee->basic_salary, 0, ',', '.'),
                $employee->isBpjsEligible() ? 'Ya' : 'Tidak',
                $employee->isLeaveEligible() ? 'Ya' : 'Tidak',
                ucfirst($employee->status),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Data Induk Tenaga Kerja',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Data Induk Tenaga Kerja',
            headings: [
                'NIK', 'Nama Lengkap', 'Email', 'Telepon', 'Jabatan', 'Divisi',
                'Entitas Kerja', 'Kontrak Mulai', 'Kontrak Berakhir', 'Gaji Pokok',
                'BPJS', 'Hak Cuti', 'Status',
            ],
            rows: $rows,
            filters: $this->filterValues($request),
        );
    }

    /**
     * Rekap kontrak yang akan berakhir (H-30 / H-14).
     */
    public function exportExpiring(Request $request, ExportService $exporter)
    {
        $days = (int) $request->integer('days', 30);

        $rows = Employee::active()
            ->expiringWithin($days)
            ->with(['employmentType', 'department'])
            ->orderBy('contract_end')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->nik,
                $employee->full_name,
                $employee->department?->name,
                $employee->employmentType?->name,
                $employee->contract_end?->format('d/m/Y'),
                (int) $employee->daysUntilContractEnd(),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Rekap Kontrak Expiring',
            format: (string) $request->string('format', 'xlsx'),
            title: "Rekap Kontrak Berakhir H-{$days}",
            headings: ['NIK', 'Nama', 'Divisi', 'Entitas Kerja', 'Berakhir', 'Sisa Hari'],
            rows: $rows,
            filters: ['rentang' => "H-{$days}"],
        );
    }

    private function filtered(Request $request): Builder
    {
        return Employee::query()
            ->when($request->string('search')->toString(), function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->integer('employment_type_id'), fn (Builder $query, int $id) => $query->where('employment_type_id', $id))
            ->when($request->integer('department_id'), fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when($request->string('category')->toString(), fn (Builder $query, string $category) => $query->whereHas('employmentType', fn (Builder $inner) => $inner->where('category', $category)))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @return array<string, mixed>
     */
    private function filterValues(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString() ?: null,
            'employment_type_id' => $request->integer('employment_type_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'employmentTypes' => EmploymentType::orderBy('id')->get(['id', 'name', 'code', 'category', 'duration_months'])->all(),
            'departments' => Department::orderBy('name')->get(['id', 'name'])->all(),
            'statuses' => ['active', 'inactive', 'expired', 'resigned'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'nik' => ['required', 'string', 'max:50', Rule::unique('employees', 'nik')->ignore($employee)],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:100'],
            'employment_type_id' => ['required', 'exists:employment_types,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'join_date' => ['required', 'date'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'expired', 'resigned'])],
        ]);
    }
}
