<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    public const GENDERS = [
        'male' => 'Laki-laki',
        'female' => 'Perempuan',
    ];

    public const RELIGIONS = [
        'islam' => 'Islam',
        'kristen' => 'Kristen Protestan',
        'katolik' => 'Katolik',
        'hindu' => 'Hindu',
        'buddha' => 'Buddha',
        'konghucu' => 'Konghucu',
    ];

    /** Mengikuti isian kolom status perkawinan pada KTP. */
    public const MARITAL_STATUSES = [
        'belum_kawin' => 'Belum Kawin',
        'kawin' => 'Kawin',
        'cerai_hidup' => 'Cerai Hidup',
        'cerai_mati' => 'Cerai Mati',
    ];

    /**
     * Isian data diri yang dihitung dalam kelengkapan profil. Departemen,
     * posisi, tanggal bergabung, dan status kerja sama diatur HR sehingga
     * tidak ikut dihitung di sini.
     *
     * @var array<string, string>
     */
    public const PROFILE_FIELDS = [
        'full_name' => 'Nama lengkap',
        'ktp_number' => 'No KTP',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'gender' => 'Jenis kelamin',
        'religion' => 'Agama',
        'marital_status' => 'Status pernikahan',
        'dependents_count' => 'Jumlah tanggungan se-KK',
        'ktp_address' => 'Alamat sesuai KTP',
        'domicile_address' => 'Alamat domisili',
        'phone' => 'No WhatsApp',
        'email' => 'Email aktif',
        'emergency_contact_name' => 'Kontak darurat',
        'emergency_contact_relation' => 'Hubungan kontak darurat',
        'emergency_contact_phone' => 'Nomor kontak darurat',
    ];

    protected $fillable = [
        'user_id',
        'employment_type_id',
        'department_id',
        'nik',
        'full_name',
        'ktp_number',
        'birth_place',
        'birth_date',
        'gender',
        'religion',
        'marital_status',
        'dependents_count',
        'ktp_address',
        'domicile_address',
        'email',
        'phone',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',
        'position',
        'ptkp_status',
        'join_date',
        'contract_start',
        'contract_end',
        'basic_salary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'dependents_count' => 'integer',
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

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function inventoryLoans(): HasMany
    {
        return $this->hasMany(InventoryLoan::class);
    }

    /** Pinjaman yang belum tuntas — penghambat clearance saat exit. */
    public function openInventoryLoans(): HasMany
    {
        return $this->inventoryLoans()->whereIn('status', InventoryLoan::OPEN_STATUSES);
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

    /**
     * Label isian data diri yang masih kosong.
     *
     * @return list<string>
     */
    public function missingProfileFields(): array
    {
        $missing = [];

        foreach (self::PROFILE_FIELDS as $field => $label) {
            $value = $this->getAttribute($field);

            if ($value === null || $value === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Label dokumen wajib yang belum diunggah.
     *
     * @return list<string>
     */
    public function missingRequiredDocuments(): array
    {
        $uploaded = $this->relationLoaded('documents')
            ? $this->documents->pluck('type')->all()
            : $this->documents()->pluck('type')->all();

        return array_values(array_map(
            fn (string $type) => EmployeeDocument::TYPES[$type]['label'],
            array_diff(EmployeeDocument::requiredTypes(), $uploaded),
        ));
    }

    /**
     * Persentase kelengkapan gabungan isian data diri dan dokumen wajib.
     */
    public function profileCompletion(): int
    {
        $total = count(self::PROFILE_FIELDS) + count(EmployeeDocument::requiredTypes());
        $missing = count($this->missingProfileFields()) + count($this->missingRequiredDocuments());

        return (int) floor(($total - $missing) / $total * 100);
    }

    public function daysUntilContractEnd(): ?int
    {
        return $this->contract_end
            ? now()->startOfDay()->diffInDays($this->contract_end, false)
            : null;
    }
}
