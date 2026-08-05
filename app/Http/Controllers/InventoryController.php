<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Services\ExportService;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modul 1 — Manajemen peminjaman inventaris.
 *
 * Katalog aset dikelola HR; pegawai mengajukan pinjaman lewat portal
 * mandiri, HR menyetujui, menyerahkan, lalu mencatat pengembalian.
 */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Konsol HR — katalog aset dan seluruh pinjaman.
     */
    public function index(Request $request): Response
    {
        $items = InventoryItem::query()
            ->withSum(
                ['loans as held_quantity' => fn (Builder $query) => $query->whereIn('status', InventoryLoan::HOLDING_STATUSES)],
                'quantity',
            )
            ->when($request->string('category')->toString(), fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($request->string('item_status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => $this->presentItem($item))
            ->all();

        $loans = InventoryLoan::query()
            ->with(['item', 'employee.department'])
            ->when($request->string('loan_status')->toString(), fn (Builder $query, string $status) => $status === 'overdue'
                ? $query->overdue()
                : $query->where('status', $status))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->whereHas(
                'employee',
                fn (Builder $inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"),
            ))
            // Yang menunggu keputusan naik ke atas, sisanya menurut jatuh tempo.
            ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InventoryLoan $loan) => $this->presentLoan($loan));

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'loans' => $loans,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'category' => $request->string('category')->toString() ?: null,
                'item_status' => $request->string('item_status')->toString() ?: null,
                'loan_status' => $request->string('loan_status')->toString() ?: null,
            ],
            'options' => [
                'categories' => InventoryItem::CATEGORIES,
                'conditions' => InventoryItem::CONDITIONS,
                'conditionLabels' => InventoryItem::CONDITION_LABELS,
                'itemStatuses' => InventoryItem::STATUSES,
                'itemStatusLabels' => InventoryItem::STATUS_LABELS,
                'loanStatusLabels' => InventoryLoan::STATUS_LABELS,
                'employees' => Employee::active()
                    ->orderBy('full_name')
                    ->get(['id', 'full_name', 'nik'])
                    ->map(fn (Employee $employee) => [
                        'id' => $employee->id,
                        'label' => "{$employee->full_name} · {$employee->nik}",
                    ])
                    ->all(),
            ],
            'stats' => [
                'totalItems' => InventoryItem::count(),
                'totalUnits' => (int) InventoryItem::sum('quantity'),
                'borrowed' => (int) InventoryLoan::where('status', 'borrowed')->sum('quantity'),
                'pending' => InventoryLoan::where('status', 'requested')->count(),
                'overdue' => InventoryLoan::overdue()->count(),
            ],
        ]);
    }

    /**
     * Portal mandiri — pinjaman milik pengguna sendiri.
     */
    public function mine(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $items = InventoryItem::lendable()
            ->withSum(
                ['loans as held_quantity' => fn (Builder $query) => $query->whereIn('status', InventoryLoan::HOLDING_STATUSES)],
                'quantity',
            )
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'label' => "{$item->code} · {$item->name}",
                'category' => $item->category,
                'available' => $item->availableQuantity(),
            ])
            ->all();

        $loans = $employee->inventoryLoans()
            ->with('item')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventoryLoan $loan) => $this->presentLoan($loan))
            ->all();

        return Inertia::render('Inventory/Mine', [
            'items' => $items,
            'loans' => $loans,
            'summary' => [
                'open' => count(array_filter($loans, fn (array $loan) => in_array($loan['status'], InventoryLoan::OPEN_STATUSES, true))),
                'overdue' => count(array_filter($loans, fn (array $loan) => $loan['isOverdue'])),
            ],
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        InventoryItem::create($this->validatedItem($request));

        return back()->with('success', 'Aset berhasil ditambahkan ke katalog.');
    }

    public function updateItem(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $this->validatedItem($request, $item);
        $held = $item->quantity - $this->inventory->availableQuantity($item);

        if ($data['quantity'] < $held) {
            return back()->with('error', "Jumlah unit tidak bisa kurang dari {$held} unit yang sedang dipinjam.");
        }

        $item->update($data);

        return back()->with('success', 'Data aset diperbarui.');
    }

    public function destroyItem(InventoryItem $item): RedirectResponse
    {
        if ($item->loans()->open()->exists()) {
            return back()->with('error', 'Aset masih memiliki pinjaman berjalan dan tidak dapat dihapus.');
        }

        $item->delete();

        return back()->with('success', 'Aset dihapus dari katalog.');
    }

    /**
     * Pengajuan pinjaman oleh pegawai untuk dirinya sendiri.
     */
    public function requestLoan(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $this->inventory->request([
            ...$this->validatedLoan($request),
            'employee_id' => $employee->id,
        ]);

        return back()->with('success', 'Pengajuan peminjaman dikirim, menunggu persetujuan HR.');
    }

    /**
     * HR mencatatkan pinjaman atas nama pegawai — langsung disetujui.
     */
    public function storeLoan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]) + $this->validatedLoan($request);

        $loan = $this->inventory->request($data);
        $this->inventory->approve($loan, (int) $request->user()->id, 'Dicatat langsung oleh HR.');

        return back()->with('success', 'Pinjaman dicatat dan langsung disetujui.');
    }

    /**
     * Satu pintu untuk seluruh transisi status pinjaman.
     */
    public function updateLoan(Request $request, InventoryLoan $loan): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'hand_over', 'return', 'lost'])],
            'note' => ['nullable', 'string', 'max:500'],
            'condition' => ['nullable', Rule::in(InventoryItem::CONDITIONS)],
        ]);

        $userId = (int) $request->user()->id;
        $note = $data['note'] ?? null;

        match ($data['action']) {
            'approve' => $this->inventory->approve($loan, $userId, $note),
            'reject' => $this->inventory->reject($loan, $userId, $note),
            'hand_over' => $this->inventory->handOver($loan, $data['condition'] ?? 'good'),
            'return' => $this->inventory->returnItem($loan, $data['condition'] ?? 'good', $note),
            'lost' => $this->inventory->markLost($loan, $note),
        };

        return back()->with('success', match ($data['action']) {
            'approve' => 'Pinjaman disetujui.',
            'reject' => 'Pengajuan ditolak.',
            'hand_over' => 'Serah terima dicatat, barang berpindah ke peminjam.',
            'return' => 'Pengembalian dicatat.',
            'lost' => 'Barang ditandai hilang dan stok disesuaikan.',
        });
    }

    /**
     * Pegawai membatalkan pengajuannya sendiri selagi belum diputuskan.
     */
    public function cancelLoan(Request $request, InventoryLoan $loan): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if($loan->employee_id !== $employee->id, 403, 'Bukan pengajuan Anda.');

        if ($loan->status !== 'requested') {
            return back()->with('error', 'Pengajuan yang sudah diputuskan tidak dapat dibatalkan.');
        }

        $loan->delete();

        return back()->with('success', 'Pengajuan dibatalkan.');
    }

    public function export(Request $request, ExportService $exporter)
    {
        $rows = InventoryLoan::with(['item', 'employee.department'])
            ->when($request->string('loan_status')->toString(), fn (Builder $query, string $status) => $status === 'overdue'
                ? $query->overdue()
                : $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventoryLoan $loan) => [
                $loan->item?->code,
                $loan->item?->name,
                $loan->employee?->nik,
                $loan->employee?->full_name,
                $loan->employee?->department?->name,
                $loan->quantity,
                InventoryLoan::STATUS_LABELS[$loan->status] ?? $loan->status,
                $loan->due_date?->format('d/m/Y'),
                $loan->handed_over_at?->format('d/m/Y H:i') ?? '-',
                $loan->returned_at?->format('d/m/Y H:i') ?? '-',
                $loan->isOverdue() ? 'Ya' : 'Tidak',
                $loan->purpose,
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Peminjaman Inventaris',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Laporan Peminjaman Inventaris',
            headings: [
                'Kode Aset', 'Nama Aset', 'NIK', 'Peminjam', 'Divisi', 'Jumlah',
                'Status', 'Jatuh Tempo', 'Diserahkan', 'Dikembalikan', 'Terlambat', 'Keperluan',
            ],
            rows: $rows,
            filters: ['status' => $request->string('loan_status')->toString() ?: 'semua'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'category' => $item->category,
            'brand' => $item->brand,
            'serialNumber' => $item->serial_number,
            'quantity' => $item->quantity,
            'available' => $item->availableQuantity(),
            'condition' => $item->condition,
            'status' => $item->status,
            'location' => $item->location,
            'purchasePrice' => $item->purchase_price !== null ? (float) $item->purchase_price : null,
            'purchaseDate' => $item->purchase_date?->toDateString(),
            'notes' => $item->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLoan(InventoryLoan $loan): array
    {
        return [
            'id' => $loan->id,
            'itemId' => $loan->inventory_item_id,
            'item' => $loan->item?->name,
            'itemCode' => $loan->item?->code,
            'employee' => $loan->employee?->full_name,
            'nik' => $loan->employee?->nik,
            'department' => $loan->employee?->department?->name,
            'quantity' => $loan->quantity,
            'status' => $loan->status,
            'statusLabel' => InventoryLoan::STATUS_LABELS[$loan->status] ?? $loan->status,
            'purpose' => $loan->purpose,
            'dueDate' => $loan->due_date?->translatedFormat('d M Y'),
            'daysToDue' => $loan->daysToDue(),
            'isOverdue' => $loan->isOverdue(),
            'handedOverAt' => $loan->handed_over_at?->translatedFormat('d M Y H:i'),
            'returnedAt' => $loan->returned_at?->translatedFormat('d M Y H:i'),
            'conditionOut' => $loan->condition_out,
            'conditionIn' => $loan->condition_in,
            'decisionNote' => $loan->decision_note,
            'returnNote' => $loan->return_note,
            'actions' => InventoryService::TRANSITIONS[$loan->status] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request, ?InventoryItem $item = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('inventory_items', 'code')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(InventoryItem::CATEGORIES)],
            'brand' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'condition' => ['required', Rule::in(InventoryItem::CONDITIONS)],
            'status' => ['required', Rule::in(InventoryItem::STATUSES)],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedLoan(Request $request): array
    {
        return $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'purpose' => ['required', 'string', 'max:500'],
            'due_date' => ['required', 'date', 'after_or_equal:'.CarbonImmutable::today()->toDateString()],
        ]);
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee()->first();

        abort_if(! $employee, 403, 'Akun Anda belum tertaut ke data tenaga kerja.');

        return $employee;
    }
}
