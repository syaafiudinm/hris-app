<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'employment_type_id',
        'department_id',
        'nik',
        'full_name',
        'email',
        'phone',
        'position',
        'join_date',
        'contract_start',
        'contract_end',
        'basic_salary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mitraPayrollSchema(): HasOne
    {
        return $this->hasOne(MitraPayrollSchema::class);
    }

    public function exit(): HasOne
    {
        return $this->hasOne(EmployeeExit::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Kontrak yang berakhir dalam rentang hari ke depan (default H-30).
     */
    public function scopeExpiringWithin(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('contract_end')
            ->whereBetween('contract_end', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function isMitra(): bool
    {
        return $this->employmentType?->category === 'mitra';
    }

    /**
     * Rule engine: Probation & Mitra tidak berhak cuti tahunan.
     */
    public function isLeaveEligible(): bool
    {
        return (bool) $this->employmentType?->is_leave_eligible;
    }

    /**
     * Rule engine: Probation & Mitra tidak didaftarkan BPJS.
     */
    public function isBpjsEligible(): bool
    {
        return (bool) $this->employmentType?->is_bpjs_eligible;
    }

    public function daysUntilContractEnd(): ?int
    {
        return $this->contract_end
            ? now()->startOfDay()->diffInDays($this->contract_end, false)
            : null;
    }
}
