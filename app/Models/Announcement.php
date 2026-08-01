<?php

namespace App\Models;

use App\Models\Concerns\TargetsAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use TargetsAudience;

    public const CATEGORIES = ['info', 'policy', 'urgent'];

    protected $fillable = [
        'title',
        'body',
        'category',
        'target_type',
        'target_department_id',
        'target_category',
        'is_pinned',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Hanya pengumuman yang sudah terbit dan tanggalnya tidak di masa depan.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
