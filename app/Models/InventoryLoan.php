<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLoan extends Model
{
    /** Status yang masih menahan stok barang. */
    public const HOLDING_STATUSES = ['approved', 'borrowed'];

    /** Status yang berarti pinjaman belum selesai (dipakai untuk clearance). */
    public const OPEN_STATUSES = ['requested', 'approved', 'borrowed'];

    public const STATUS_LABELS = [
        'requested' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'borrowed' => 'Sedang dipinjam',
        'returned' => 'Sudah dikembalikan',
        'rejected' => 'Ditolak',
        'lost' => 'Hilang / rusak berat',
    ];

    protected $fillable = [
        'inventory_item_id',
        'employee_id',
        'quantity',
        'status',
        'purpose',
        'due_date',
        'handed_over_at',
        'returned_at',
        'condition_out',
        'condition_in',
        'decision_note',
        'return_note',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'due_date' => 'date',
            'handed_over_at' => 'datetime',
            'returned_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'borrowed')
            ->whereDate('due_date', '<', CarbonImmutable::today()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'borrowed'
            && $this->due_date !== null
            && $this->due_date->isBefore(CarbonImmutable::today());
    }

    /** Sisa hari sampai jatuh tempo; negatif berarti sudah lewat. */
    public function daysToDue(): int
    {
        return (int) CarbonImmutable::today()->diffInDays($this->due_date, false);
    }
}
