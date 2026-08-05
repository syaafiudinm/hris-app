<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Peminjaman inventaris — satu-satunya tempat transisi status pinjaman
 * dan pengecekan ketersediaan stok dijalankan.
 */
class InventoryService
{
    /**
     * Transisi status yang diizinkan. Apa pun di luar peta ini ditolak,
     * sehingga alur pinjam-kembali tidak bisa dilompati dari UI.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected'],
        'approved' => ['borrowed', 'rejected'],
        'borrowed' => ['returned', 'lost'],
        'returned' => [],
        'rejected' => [],
        'lost' => [],
    ];

    /**
     * Ajukan pinjaman. Stok belum ditahan sampai HR menyetujui, jadi
     * ketersediaan dicek ulang saat persetujuan.
     *
     * @param  array{inventory_item_id: int, employee_id: int, quantity: int, purpose: string, due_date: string}  $data
     */
    public function request(array $data): InventoryLoan
    {
        $item = InventoryItem::findOrFail($data['inventory_item_id']);

        if ($item->status !== 'active') {
            throw ValidationException::withMessages([
                'inventory_item_id' => "{$item->name} sedang tidak dapat dipinjam (status: ".
                    (InventoryItem::STATUS_LABELS[$item->status] ?? $item->status).').',
            ]);
        }

        if ($data['quantity'] > $item->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Jumlah melebihi total unit {$item->name} ({$item->quantity} unit).",
            ]);
        }

        return InventoryLoan::create([
            ...$data,
            'status' => 'requested',
        ]);
    }

    /**
     * Setujui pinjaman. Stok dikunci di dalam transaksi supaya dua
     * persetujuan bersamaan tidak membuat unit ter-booking ganda.
     */
    public function approve(InventoryLoan $loan, int $userId, ?string $note = null): InventoryLoan
    {
        $this->assertTransition($loan, 'approved');

        return DB::transaction(function () use ($loan, $userId, $note) {
            $item = InventoryItem::lockForUpdate()->findOrFail($loan->inventory_item_id);
            $available = $this->availableQuantity($item, exceptLoanId: $loan->id);

            if ($loan->quantity > $available) {
                throw ValidationException::withMessages([
                    'status' => "Stok {$item->name} tinggal {$available} unit, tidak cukup untuk {$loan->quantity} unit.",
                ]);
            }

            $loan->update([
                'status' => 'approved',
                'decision_note' => $note,
                'decided_by' => $userId,
                'decided_at' => CarbonImmutable::now(),
            ]);

            return $loan->refresh();
        });
    }

    public function reject(InventoryLoan $loan, int $userId, ?string $note = null): InventoryLoan
    {
        $this->assertTransition($loan, 'rejected');

        $loan->update([
            'status' => 'rejected',
            'decision_note' => $note,
            'decided_by' => $userId,
            'decided_at' => CarbonImmutable::now(),
        ]);

        return $loan->refresh();
    }

    /** Serah terima fisik — barang berpindah ke tangan peminjam. */
    public function handOver(InventoryLoan $loan, string $conditionOut): InventoryLoan
    {
        $this->assertTransition($loan, 'borrowed');

        $loan->update([
            'status' => 'borrowed',
            'condition_out' => $conditionOut,
            'handed_over_at' => CarbonImmutable::now(),
        ]);

        return $loan->refresh();
    }

    /**
     * Pengembalian. Kondisi barang saat kembali ikut memperbarui kondisi
     * master aset agar rekap tidak perlu disamakan manual.
     */
    public function returnItem(InventoryLoan $loan, string $conditionIn, ?string $note = null): InventoryLoan
    {
        $this->assertTransition($loan, 'returned');

        return DB::transaction(function () use ($loan, $conditionIn, $note) {
            $loan->update([
                'status' => 'returned',
                'condition_in' => $conditionIn,
                'return_note' => $note,
                'returned_at' => CarbonImmutable::now(),
            ]);

            $item = $loan->item;

            if ($item && $this->conditionRank($conditionIn) > $this->conditionRank($item->condition)) {
                $item->update([
                    'condition' => $conditionIn,
                    // Barang rusak berat langsung ditarik dari peredaran.
                    'status' => $conditionIn === 'damaged' ? 'maintenance' : $item->status,
                ]);
            }

            return $loan->refresh();
        });
    }

    /** Barang dinyatakan hilang — stok dikurangi permanen. */
    public function markLost(InventoryLoan $loan, ?string $note = null): InventoryLoan
    {
        $this->assertTransition($loan, 'lost');

        return DB::transaction(function () use ($loan, $note) {
            $loan->update([
                'status' => 'lost',
                'return_note' => $note,
                'returned_at' => CarbonImmutable::now(),
            ]);

            $item = $loan->item;

            if ($item) {
                $remaining = max(0, $item->quantity - $loan->quantity);

                $item->update([
                    'quantity' => $remaining,
                    'status' => $remaining === 0 ? 'retired' : $item->status,
                ]);
            }

            return $loan->refresh();
        });
    }

    /**
     * Sisa unit yang bisa dipinjam saat ini.
     *
     * @param  int|null  $exceptLoanId  Pinjaman yang sedang diproses, agar
     *                                  tidak menghitung dirinya sendiri.
     */
    public function availableQuantity(InventoryItem $item, ?int $exceptLoanId = null): int
    {
        $held = $item->loans()
            ->whereIn('status', InventoryLoan::HOLDING_STATUSES)
            ->when($exceptLoanId, fn ($query, int $id) => $query->where('id', '!=', $id))
            ->sum('quantity');

        return max(0, $item->quantity - (int) $held);
    }

    private function assertTransition(InventoryLoan $loan, string $target): void
    {
        $allowed = self::TRANSITIONS[$loan->status] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Pinjaman berstatus "%s" tidak dapat diubah menjadi "%s".',
                    InventoryLoan::STATUS_LABELS[$loan->status] ?? $loan->status,
                    InventoryLoan::STATUS_LABELS[$target] ?? $target,
                ),
            ]);
        }
    }

    /** Urutan keparahan kondisi — dipakai untuk menentukan penurunan mutu. */
    private function conditionRank(?string $condition): int
    {
        return match ($condition) {
            'damaged' => 2,
            'minor' => 1,
            default => 0,
        };
    }
}
