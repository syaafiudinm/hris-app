<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExit extends Model
{
    public const EXIT_TYPES = ['resign', 'contract_end', 'termination', 'retirement'];

    public const EXIT_TYPE_LABELS = [
        'resign' => 'Mengundurkan Diri',
        'contract_end' => 'Kontrak Berakhir',
        'termination' => 'Pemutusan Hubungan Kerja',
        'retirement' => 'Pensiun',
    ];

    protected $fillable = [
        'employee_id',
        'exit_type',
        'submitted_date',
        'last_working_date',
        'reason',
        'notes',
        'status',
        'paklaring_number',
        'paklaring_issued_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_date' => 'date',
            'last_working_date' => 'date',
            'paklaring_issued_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'processed_by');
    }

    public function typeLabel(): string
    {
        return self::EXIT_TYPE_LABELS[$this->exit_type] ?? $this->exit_type;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Masa kerja dari tanggal bergabung sampai hari kerja terakhir.
     *
     * @return array{years: int, months: int, label: string}
     */
    public function tenure(): array
    {
        $start = $this->employee?->join_date;

        if (! $start) {
            return ['years' => 0, 'months' => 0, 'label' => '-'];
        }

        $months = (int) $start->diffInMonths($this->last_working_date);
        $years = intdiv($months, 12);
        $remaining = $months % 12;

        $parts = [];
        if ($years > 0) {
            $parts[] = "{$years} tahun";
        }
        if ($remaining > 0 || $parts === []) {
            $parts[] = "{$remaining} bulan";
        }

        return [
            'years' => $years,
            'months' => $remaining,
            'label' => implode(' ', $parts),
        ];
    }
}
