<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'period_year',
        'period_month',
        'payout_type',
        'basic_amount',
        'allowance_amount',
        'overtime_amount',
        'gross_amount',
        'bpjs_employee_deduction',
        'bpjs_company_contribution',
        'pph_deduction',
        'other_deduction',
        'net_payout',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'basic_amount' => 'decimal:2',
            'allowance_amount' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'bpjs_employee_deduction' => 'decimal:2',
            'bpjs_company_contribution' => 'decimal:2',
            'pph_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_payout' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
