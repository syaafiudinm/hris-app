<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Applicant extends Model
{
    protected $fillable = [
        'job_vacancy_id',
        'converted_employee_id',
        'full_name',
        'email',
        'phone',
        'cv_path',
        'stage',
        'notes',
        'stage_history',
        'stage_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'array',
            'stage_history' => 'array',
            'stage_changed_at' => 'datetime',
        ];
    }

    public function jobVacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class);
    }

    public function convertedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'converted_employee_id');
    }

    /**
     * Record a stage transition in the audit history.
     */
    public function recordStageChange(string $from, string $to, ?string $changedBy = null): void
    {
        $history = $this->stage_history ?? [];

        $history[] = [
            'from' => $from,
            'to' => $to,
            'changed_by' => $changedBy,
            'changed_at' => now()->toIso8601String(),
        ];

        $this->update([
            'stage' => $to,
            'stage_history' => $history,
            'stage_changed_at' => now(),
        ]);
    }
}
