<?php

namespace App\Models;

use App\Models\Concerns\TargetsAudience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocument extends Model
{
    use TargetsAudience;

    public const DOC_TYPES = ['sop', 'peraturan', 'panduan', 'formulir'];

    public const DOC_TYPE_LABELS = [
        'sop' => 'SOP',
        'peraturan' => 'Peraturan Perusahaan',
        'panduan' => 'Panduan',
        'formulir' => 'Formulir',
    ];

    protected $fillable = [
        'title',
        'description',
        'doc_type',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'version',
        'target_type',
        'target_department_id',
        'target_category',
        'download_count',
        'uploaded_by',
    ];

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    /**
     * Ukuran berkas dalam satuan yang terbaca manusia.
     */
    public function humanFileSize(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }

    public function typeLabel(): string
    {
        return self::DOC_TYPE_LABELS[$this->doc_type] ?? $this->doc_type;
    }
}
