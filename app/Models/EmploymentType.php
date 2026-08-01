<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'duration_months',
        'is_leave_eligible',
        'is_bpjs_eligible',
        'annual_leave_quota',
    ];

    protected function casts(): array
    {
        return [
            'is_leave_eligible' => 'boolean',
            'is_bpjs_eligible' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
