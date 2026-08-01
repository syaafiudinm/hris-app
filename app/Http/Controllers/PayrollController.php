<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\ExportService;
use App\Services\PayrollRunService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        [$year, $month] = $this->period($request);

        $payrolls = $this->filtered($request)
            ->with(['employee.department', 'employee.employmentType'])
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->orderBy('employees.full_name')
            ->select('payrolls.*')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Payroll $payroll) => [
                'id' => $payroll->id,
                'employee' => $payroll->employee?->full_name,
                'nik' => $payroll->employee?->nik,
                'department' => $payroll->employee?->department?->name,
                'type' => $payroll->employee?->employmentType?->name,
                'payoutType' => $payroll->payout_type,
                'gross' => (float) $payroll->gross_amount,
                'bpjs' => (float) $payroll->bpjs_employee_deduction,
                'pph' => (float) $payroll->pph_deduction,
                'net' => (float) $payroll->net_payout,
                'status' => $payroll->status,
            ]);

        $totals = (clone $this->filtered($request))
            ->selectRaw('payout_type, sum(gross_amount) as gross, sum(net_payout) as net, sum(bpjs_company_contribution) as company_bpjs, sum(pph_deduction) as pph, count(*) as total')
            ->groupBy('payout_type')
            ->get()
            ->keyBy('payout_type');

        return Inertia::render('Payroll/Index', [
            'payrolls' => $payrolls,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'search' => $request->string('search')->toString() ?: null,
                'payout_type' => $request->string('payout_type')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ],
            'summary' => [
                'employeeNet' => (float) ($totals['employee']->net ?? 0),
                'mitraNet' => (float) ($totals['mitra']->net ?? 0),
                'employeeCount' => (int) ($totals['employee']->total ?? 0),
                'mitraCount' => (int) ($totals['mitra']->total ?? 0),
                'companyBpjs' => (float) ($totals['employee']->company_bpjs ?? 0),
                'totalPph' => (float) (($totals['employee']->pph ?? 0) + ($totals['mitra']->pph ?? 0)),
                'periodLabel' => CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y'),
            ],
            'options' => [
                'years' => range(CarbonImmutable::now()->year - 3, CarbonImmutable::now()->year),
                'statuses' => ['draft', 'approved', 'paid'],
            ],
        ]);
    }

    /**
     * Jalankan mesin penggajian untuk satu periode.
     */
    public function run(Request $request, PayrollRunService $runner): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'overwrite_paid' => ['nullable', 'boolean'],
        ]);

        $result = $runner->run(
            (int) $data['year'],
            (int) $data['month'],
            (bool) ($data['overwrite_paid'] ?? false),
        );

        return back()->with('success', sprintf(
            '%d slip dibuat, %d diperbarui, %d dilewati. Total netto %s.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
            'Rp '.number_format($result['total'], 0, ',', '.'),
        ));
    }

    public function show(Payroll $payroll): Response
    {
        $payroll->load(['employee.department', 'employee.employmentType', 'employee.mitraPayrollSchema']);

        return Inertia::render('Payroll/Show', [
            'payroll' => $this->detail($payroll),
        ]);
    }

    public function updateStatus(Request $request, Payroll $payroll): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'approved', 'paid'])],
        ]);

        $payroll->update($data);

        return back()->with('success', "Status slip diubah menjadi {$data['status']}.");
    }

    /**
     * Slip gaji karyawan / payment voucher mitra dalam PDF.
     */
    public function document(Request $request, Payroll $payroll)
    {
        // Karyawan hanya boleh mengunduh slipnya sendiri; HR boleh semua.
        $user = $request->user();
        abort_if(
            ! $user->isSuperAdmin() && $payroll->employee_id !== $user->employee?->id,
            403,
            'Anda hanya dapat mengunduh slip milik sendiri.',
        );

        $payroll->load(['employee.department', 'employee.employmentType', 'employee.mitraPayrollSchema']);

        $isMitra = $payroll->payout_type === 'mitra';

        $pdf = Pdf::loadView($isMitra ? 'documents.payment-voucher' : 'documents.payslip', [
            'payroll' => $payroll,
            'employee' => $payroll->employee,
            'schema' => $payroll->employee?->mitraPayrollSchema,
            'periodLabel' => CarbonImmutable::create($payroll->period_year, $payroll->period_month, 1)
                ->translatedFormat('F Y'),
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
        ])->setPaper('a4');

        $prefix = $isMitra ? 'payment-voucher' : 'slip-gaji';
        $fileName = sprintf(
            '%s-%s-%d%02d.pdf',
            $prefix,
            $payroll->employee?->nik ?? $payroll->employee_id,
            $payroll->period_year,
            $payroll->period_month,
        );

        return $pdf->download($fileName);
    }

    /**
     * Rekap gaji bulanan.
     */
    public function export(Request $request, ExportService $exporter)
    {
        [$year, $month] = $this->period($request);

        $rows = $this->filtered($request)
            ->with(['employee.department', 'employee.employmentType'])
            ->get()
            ->map(fn (Payroll $payroll) => [
                $payroll->employee?->nik,
                $payroll->employee?->full_name,
                $payroll->employee?->department?->name,
                $payroll->employee?->employmentType?->name,
                (float) $payroll->basic_amount,
                (float) $payroll->allowance_amount,
                (float) $payroll->overtime_amount,
                (float) $payroll->gross_amount,
                (float) $payroll->bpjs_employee_deduction,
                (float) $payroll->bpjs_company_contribution,
                (float) $payroll->pph_deduction,
                (float) $payroll->net_payout,
                ucfirst($payroll->status),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Rekap Gaji Bulanan',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Rekap Gaji '.CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y'),
            headings: [
                'NIK', 'Nama', 'Divisi', 'Entitas Kerja', 'Gaji Pokok', 'Tunjangan',
                'Lembur', 'Bruto', 'BPJS Pekerja', 'BPJS Perusahaan', 'PPh', 'Netto', 'Status',
            ],
            rows: $rows,
            filters: ['periode' => sprintf('%02d/%d', $month, $year)],
        );
    }

    /**
     * File transfer bank massal — CSV siap unggah.
     */
    public function exportBankTransfer(Request $request, ExportService $exporter)
    {
        [$year, $month] = $this->period($request);

        $rows = $this->filtered($request)
            ->with('employee')
            ->get()
            ->map(fn (Payroll $payroll) => [
                $payroll->employee?->nik,
                $payroll->employee?->full_name,
                (int) round((float) $payroll->net_payout),
                sprintf('GAJI %02d/%d', $month, $year),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'File Transfer Bank',
            format: 'csv',
            title: 'File Transfer Bank',
            headings: ['NIK', 'Nama Penerima', 'Nominal', 'Berita'],
            rows: $rows,
            filters: ['periode' => sprintf('%02d/%d', $month, $year)],
        );
    }

    /**
     * Rekap pajak PPh 21 / 23.
     */
    public function exportTax(Request $request, ExportService $exporter)
    {
        [$year, $month] = $this->period($request);

        $rows = $this->filtered($request)
            ->with(['employee.employmentType', 'employee.mitraPayrollSchema'])
            ->get()
            ->map(fn (Payroll $payroll) => [
                $payroll->employee?->nik,
                $payroll->employee?->full_name,
                $payroll->employee?->employmentType?->name,
                $payroll->payout_type === 'mitra'
                    ? str_replace('_', ' ', (string) $payroll->employee?->mitraPayrollSchema?->tax_scheme)
                    : 'PPh 21 TER',
                (float) $payroll->gross_amount,
                (float) $payroll->pph_deduction,
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Rekap Pajak',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Rekap PPh 21/23 '.CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y'),
            headings: ['NIK', 'Nama', 'Entitas Kerja', 'Skema Pajak', 'Bruto', 'PPh Dipotong'],
            rows: $rows,
            filters: ['periode' => sprintf('%02d/%d', $month, $year)],
        );
    }

    /**
     * Slip gaji milik karyawan yang sedang login (self-service).
     */
    public function mine(Request $request): Response
    {
        $employee = $request->user()?->employee;

        abort_if(! $employee, 403, 'Akun Anda belum tertaut ke data tenaga kerja.');

        return Inertia::render('Payroll/Mine', [
            'payrolls' => $employee->payrolls()
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->limit(24)
                ->get()
                ->map(fn (Payroll $payroll) => [
                    'id' => $payroll->id,
                    'period' => CarbonImmutable::create($payroll->period_year, $payroll->period_month, 1)
                        ->translatedFormat('F Y'),
                    'gross' => (float) $payroll->gross_amount,
                    'net' => (float) $payroll->net_payout,
                    'payoutType' => $payroll->payout_type,
                    'status' => $payroll->status,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Payroll $payroll): array
    {
        return [
            'id' => $payroll->id,
            'period' => CarbonImmutable::create($payroll->period_year, $payroll->period_month, 1)
                ->translatedFormat('F Y'),
            'employee' => [
                'name' => $payroll->employee?->full_name,
                'nik' => $payroll->employee?->nik,
                'position' => $payroll->employee?->position,
                'department' => $payroll->employee?->department?->name,
                'type' => $payroll->employee?->employmentType?->name,
                'isBpjsEligible' => (bool) $payroll->employee?->isBpjsEligible(),
            ],
            'payoutType' => $payroll->payout_type,
            'status' => $payroll->status,
            'components' => [
                'basic' => (float) $payroll->basic_amount,
                'allowance' => (float) $payroll->allowance_amount,
                'overtime' => (float) $payroll->overtime_amount,
                'gross' => (float) $payroll->gross_amount,
                'bpjsEmployee' => (float) $payroll->bpjs_employee_deduction,
                'bpjsCompany' => (float) $payroll->bpjs_company_contribution,
                'pph' => (float) $payroll->pph_deduction,
                'other' => (float) $payroll->other_deduction,
                'net' => (float) $payroll->net_payout,
            ],
            'mitraSchema' => $payroll->employee?->mitraPayrollSchema ? [
                'schemaType' => $payroll->employee->mitraPayrollSchema->schema_type,
                'rate' => (float) $payroll->employee->mitraPayrollSchema->rate_per_unit,
                'unitLabel' => $payroll->employee->mitraPayrollSchema->unit_label,
                'taxScheme' => $payroll->employee->mitraPayrollSchema->tax_scheme,
            ] : null,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = CarbonImmutable::now();

        return [
            (int) ($request->integer('year') ?: $now->year),
            (int) ($request->integer('month') ?: $now->month),
        ];
    }

    private function filtered(Request $request): Builder
    {
        [$year, $month] = $this->period($request);

        return Payroll::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->when($request->string('payout_type')->toString(), fn (Builder $query, string $type) => $query->where('payout_type', $type))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->whereHas(
                'employee',
                fn (Builder $inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"),
            ))
            ->when($request->integer('department_id'), fn (Builder $query, int $id) => $query->whereHas(
                'employee',
                fn (Builder $inner) => $inner->where('department_id', $id),
            ));
    }
}
