<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesProduct extends Model
{
    protected $fillable = ['code', 'name', 'incentive_amount', 'is_active'];

    protected function casts(): array
    {
        return [
            'incentive_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function salesRecords(): HasMany
    {
        return $this->hasMany(SalesRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
