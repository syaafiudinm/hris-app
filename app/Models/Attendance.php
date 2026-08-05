<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'clock_in_lat',
        'clock_in_long',
        'clock_in_photo',
        'clock_in_method',
        'clock_in_distance',
        'clock_in_office',
        'is_outside_radius',
        'clock_in_note',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_note',
        'is_fake_gps',
        'status',
        'work_minutes',
        'late_minutes',
    ];

    /** Label metode absensi untuk tampilan. */
    public const METHOD_LABELS = [
        'live' => 'Kamera langsung',
        'upload' => 'Unggah foto',
    ];

    public const VERIFICATION_LABELS = [
        'auto' => 'Terverifikasi otomatis',
        'pending' => 'Menunggu verifikasi HR',
        'approved' => 'Disetujui HR',
        'rejected' => 'Ditolak HR',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'verified_at' => 'datetime',
            'is_fake_gps' => 'boolean',
            'is_outside_radius' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
