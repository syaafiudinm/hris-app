<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penargetan audiens bersama untuk konten Knowledge Center.
 *
 * Satu konten dapat ditujukan ke seluruh perusahaan, satu divisi, atau satu
 * kategori entitas kerja. Penyaringan dilakukan di level query supaya karyawan
 * tidak pernah menerima konten yang bukan haknya.
 */
trait TargetsAudience
{
    public const TARGET_TYPES = ['all', 'department', 'employment_category'];

    public function scopeVisibleTo(Builder $query, ?Employee $employee): Builder
    {
        // Tanpa data karyawan, hanya konten untuk seluruh perusahaan.
        if (! $employee) {
            return $query->where('target_type', 'all');
        }

        $departmentId = $employee->department_id;
        $category = $employee->employmentType?->category;

        return $query->where(function (Builder $inner) use ($departmentId, $category) {
            $inner->where('target_type', 'all')
                ->orWhere(fn (Builder $q) => $q
                    ->where('target_type', 'department')
                    ->where('target_department_id', $departmentId))
                ->orWhere(fn (Builder $q) => $q
                    ->where('target_type', 'employment_category')
                    ->where('target_category', $category));
        });
    }

    /**
     * Label audiens untuk ditampilkan di UI.
     */
    public function audienceLabel(): string
    {
        return match ($this->target_type) {
            'department' => 'Divisi '.($this->targetDepartment?->name ?? '—'),
            'employment_category' => 'Entitas '.match ($this->target_category) {
                'probation' => 'Probation',
                'pkwt' => 'PKWT',
                'mitra' => 'Mitra',
                default => '—',
            },
            default => 'Seluruh perusahaan',
        };
    }
}
