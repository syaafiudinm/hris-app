<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MitraPayrollSchema extends Model
{
    protected $fillable = [
        'employee_id',
        'schema_type',
        'rate_per_unit',
        'unit_label',
        'tax_scheme',
        'custom_tax_percentage',
        'components',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_unit' => 'decimal:2',
            'custom_tax_percentage' => 'decimal:2',
            'components' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
