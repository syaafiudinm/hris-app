<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** Kategori aset yang lazim dipinjamkan. */
    public const CATEGORIES = [
        'elektronik',
        'kendaraan',
        'perkakas',
        'furnitur',
        'dokumen',
        'lainnya',
    ];

    public const CONDITIONS = ['good', 'minor', 'damaged'];

    public const CONDITION_LABELS = [
        'good' => 'Baik',
        'minor' => 'Rusak ringan',
        'damaged' => 'Rusak berat',
    ];

    public const STATUSES = ['active', 'maintenance', 'retired'];

    public const STATUS_LABELS = [
        'active' => 'Aktif',
        'maintenance' => 'Perbaikan',
        'retired' => 'Dihapus',
    ];

    protected $fillable = [
        'code',
        'name',
        'category',
        'brand',
        'serial_number',
        'quantity',
        'condition',
        'status',
        'location',
        'purchase_price',
        'purchase_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'purchase_price' => 'decimal:2',
            'purchase_date' => 'date',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(InventoryLoan::class);
    }

    /** Peminjaman yang masih menahan stok (disetujui atau sedang dipegang). */
    public function activeLoans(): HasMany
    {
        return $this->loans()->whereIn('status', InventoryLoan::HOLDING_STATUSES);
    }

    public function scopeLendable(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Sisa unit yang bisa dipinjam. Memakai `held_quantity` bila query
     * sudah memuatnya lewat withSum, agar tidak memicu N+1.
     */
    public function availableQuantity(): int
    {
        $held = array_key_exists('held_quantity', $this->attributes)
            ? (int) $this->attributes['held_quantity']
            : (int) $this->activeLoans()->sum('quantity');

        return max(0, $this->quantity - $held);
    }
}
