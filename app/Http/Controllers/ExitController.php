<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Services\ExitService;
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Modul 1 — Exit / Paklaring.
 */
class ExitController extends Controller
{
    public function __construct(private ExitService $exits) {}

    public function index(Request $request): Response
    {
        $exits = EmployeeExit::with([
            'employee.department',
            'employee.employmentType',
            'employee' => fn ($query) => $query->withCount('openInventoryLoans as open_loans_count'),
        ])
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('exit_type')->toString(), fn (Builder $query, string $type) => $query->where('exit_type', $type))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->whereHas(
                'employee',
                fn (Builder $inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"),
            ))
            ->orderByDesc('last_working_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EmployeeExit $exit) => $this->present($exit));

        return Inertia::render('Exits/Index', [
            'exits' => $exits,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'exit_type' => $request->string('exit_type')->toString() ?: null,
                'search' => $request->string('search')->toString() ?: null,
            ],
            'options' => [
                'exitTypes' => EmployeeExit::EXIT_TYPES,
                'exitTypeLabels' => EmployeeExit::EXIT_TYPE_LABELS,
                // Hanya karyawan aktif yang belum punya catatan exit.
                'eligibleEmployees' => Employee::active()
                    ->whereDoesntHave('exit')
                    ->with(['department', 'employmentType'])
                    ->orderBy('full_name')
                    ->get()
                    ->map(fn (Employee $employee) => [
                        'id' => $employee->id,
                        'label' => "{$employee->full_name} · {$employee->nik}",
                        'department' => $employee->department?->name,
                        'type' => $employee->employmentType?->name,
                        'contractEnd' => $employee->contract_end?->toDateString(),
                    ])
                    ->all(),
            ],
            'stats' => [
                'draft' => EmployeeExit::where('status', 'draft')->count(),
                'completed' => EmployeeExit::where('status', 'completed')->count(),
                'issued' => EmployeeExit::whereNotNull('paklaring_number')->count(),
                'expiringSoon' => Employee::active()->expiringWithin(30)->whereDoesntHave('exit')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $employee = Employee::findOrFail($data['employee_id']);

        if ($employee->exit()->exists()) {
            return back()->with('error', "{$employee->full_name} sudah memiliki catatan proses keluar.");
        }

        EmployeeExit::create($data + [
            'status' => 'draft',
            'processed_by' => $request->user()?->employee?->id,
        ]);

        return back()->with('success', "Proses keluar {$employee->full_name} dicatat sebagai draft.");
    }

    public function update(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $data = $this->validated($request, $exit);

        // Karyawan tidak boleh dipindah ke orang lain setelah tercatat.
        unset($data['employee_id']);

        $exit->update($data);

        return back()->with('success', 'Data proses keluar diperbarui.');
    }

    /**
     * Tuntaskan atau buka kembali proses keluar.
     */
    public function updateStatus(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'completed'])],
        ]);

        if ($data['status'] === 'completed') {
            // Clearance: aset perusahaan harus tuntas sebelum paklaring terbit.
            $outstanding = $exit->employee?->openInventoryLoans()->with('item')->get() ?? collect();

            if ($outstanding->isNotEmpty()) {
                return back()->with('error', sprintf(
                    'Masih ada %d peminjaman inventaris yang belum tuntas (%s). Selesaikan pengembaliannya lebih dulu.',
                    $outstanding->count(),
                    $outstanding->map(fn ($loan) => $loan->item?->name)->filter()->implode(', '),
                ));
            }

            $this->exits->complete($exit);

            return back()->with(
                'success',
                "Proses keluar dituntaskan. Nomor paklaring {$exit->fresh()->paklaring_number}.",
            );
        }

        $this->exits->reopen($exit);

        return back()->with('success', 'Proses keluar dibuka kembali dan karyawan diaktifkan lagi.');
    }

    public function destroy(EmployeeExit $exit): RedirectResponse
    {
        if ($exit->isCompleted()) {
            return back()->with(
                'error',
                'Proses yang sudah tuntas tidak dapat dihapus. Buka kembali terlebih dahulu.',
            );
        }

        $exit->delete();

        return back()->with('success', 'Draft proses keluar dihapus.');
    }

    /**
     * Cetak paklaring. Dapat dicetak ulang kapan pun karena mantan karyawan
     * sering membutuhkannya lagi (klaim JHT, melamar kerja, pengajuan kredit).
     */
    public function paklaring(EmployeeExit $exit): HttpResponse
    {
        abort_if(
            ! $exit->isCompleted(),
            403,
            'Paklaring hanya dapat diterbitkan setelah proses keluar dituntaskan.',
        );

        $exit->load(['employee.department', 'employee.employmentType', 'processedBy']);

        $pdf = Pdf::loadView('documents.paklaring', [
            'exit' => $exit,
            'employee' => $exit->employee,
            'tenure' => $exit->tenure(),
            'issuedAt' => ($exit->paklaring_issued_at ?? now())->translatedFormat('d F Y'),
        ])->setPaper('a4');

        return $pdf->download('paklaring-'.$exit->employee?->nik.'.pdf');
    }

    /**
     * Rekap proses keluar.
     */
    public function export(Request $request, ExportService $exporter): HttpResponse
    {
        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'exit_type' => $request->string('exit_type')->toString() ?: null,
        ];

        $rows = EmployeeExit::with(['employee.department', 'employee.employmentType'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['exit_type'], fn (Builder $query, string $type) => $query->where('exit_type', $type))
            ->orderByDesc('last_working_date')
            ->get()
            ->map(fn (EmployeeExit $exit) => [
                $exit->employee?->nik,
                $exit->employee?->full_name,
                $exit->employee?->department?->name,
                $exit->employee?->employmentType?->name,
                $exit->typeLabel(),
                $exit->employee?->join_date?->format('d/m/Y'),
                $exit->last_working_date->format('d/m/Y'),
                $exit->tenure()['label'],
                $exit->paklaring_number ?? '-',
                ucfirst($exit->status),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Rekap Proses Keluar',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Rekap Proses Keluar & Paklaring',
            headings: [
                'NIK', 'Nama', 'Divisi', 'Entitas Kerja', 'Jenis',
                'Bergabung', 'Hari Kerja Terakhir', 'Masa Kerja', 'No. Paklaring', 'Status',
            ],
            rows: $rows,
            filters: $filters,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EmployeeExit $exit = null): array
    {
        return $request->validate([
            'employee_id' => [
                $exit ? 'nullable' : 'required',
                'exists:employees,id',
            ],
            'exit_type' => ['required', Rule::in(EmployeeExit::EXIT_TYPES)],
            'submitted_date' => ['nullable', 'date'],
            'last_working_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EmployeeExit $exit): array
    {
        return [
            'id' => $exit->id,
            'employeeId' => $exit->employee_id,
            'name' => $exit->employee?->full_name,
            'nik' => $exit->employee?->nik,
            'department' => $exit->employee?->department?->name,
            'type' => $exit->employee?->employmentType?->name,
            'exitType' => $exit->exit_type,
            'exitTypeLabel' => $exit->typeLabel(),
            'submittedDate' => $exit->submitted_date?->toDateString(),
            'lastWorkingDate' => $exit->last_working_date->toDateString(),
            'lastWorkingLabel' => $exit->last_working_date->translatedFormat('d M Y'),
            'tenure' => $exit->tenure()['label'],
            'reason' => $exit->reason,
            'notes' => $exit->notes,
            'status' => $exit->status,
            'paklaringNumber' => $exit->paklaring_number,
            'paklaringIssuedAt' => $exit->paklaring_issued_at?->translatedFormat('d M Y'),
            // Penghambat clearance — ditampilkan agar HR tahu sebelum menekan
            // tombol tuntaskan.
            'openLoans' => (int) ($exit->employee?->open_loans_count ?? 0),
        ];
    }
}
