<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    /** Batas standar — cukup untuk scan satu-dua halaman beresolusi wajar. */
    public const DEFAULT_MAX_KB = 2048;

    /**
     * Berkas kelengkapan karyawan. Seluruhnya PDF agar seragam saat dicetak
     * dan diarsipkan; hanya foto diri yang juga menerima gambar.
     *
     * `required` menentukan hitungan kelengkapan, bukan penolakan unggah.
     *
     * @var array<string, array{label: string, required: bool, hint: ?string, mimes: list<string>, max_kb: int}>
     */
    public const TYPES = [
        'cv' => [
            'label' => 'CV Terbaru',
            'required' => true,
            'hint' => null,
            'mimes' => ['pdf'],
            'max_kb' => self::DEFAULT_MAX_KB,
        ],
        'ktp' => [
            'label' => 'KTP (Kartu Tanda Penduduk)',
            'required' => true,
            'hint' => null,
            'mimes' => ['pdf'],
            'max_kb' => self::DEFAULT_MAX_KB,
        ],
        'kk' => [
            'label' => 'KK (Kartu Keluarga)',
            'required' => true,
            'hint' => null,
            'mimes' => ['pdf'],
            'max_kb' => self::DEFAULT_MAX_KB,
        ],
        'photo' => [
            'label' => 'Foto Diri Terbaru',
            'required' => true,
            'hint' => 'Formal/semi-formal.',
            'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
            'max_kb' => self::DEFAULT_MAX_KB,
        ],
        'ijazah_paklaring' => [
            'label' => 'Ijazah Terakhir & Paklaring',
            'required' => false,
            'hint' => 'Gabungkan dalam satu PDF. Boleh menyusul.',
            'mimes' => ['pdf'],
            'max_kb' => 5120,
        ],
        'bank_account' => [
            'label' => 'Buku Rekening BCA / SS Mobile Banking',
            'required' => true,
            'hint' => 'Nama & nomor rekening harus terbaca jelas.',
            'mimes' => ['pdf'],
            'max_kb' => self::DEFAULT_MAX_KB,
        ],
        'npwp' => [
            'label' => 'NPWP',
            'required' => true,
            'hint' => null,
            'mimes' => ['pdf'],
            'max_kb' => self::DEFAULT_MAX_KB,
        ],
        'certificates' => [
            'label' => 'Sertifikat Pendukung',
            'required' => false,
            'hint' => 'Jika ada. Gabungkan semua sertifikat dalam satu PDF.',
            'mimes' => ['pdf'],
            'max_kb' => 5120,
        ],
    ];

    protected $fillable = [
        'employee_id',
        'type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return list<string>
     */
    public static function requiredTypes(): array
    {
        return array_keys(array_filter(self::TYPES, fn (array $type) => $type['required']));
    }
}
