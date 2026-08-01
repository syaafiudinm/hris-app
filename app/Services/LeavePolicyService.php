<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Aturan cuti berdasarkan entitas kerja (Masterplan §2.2 & §5.2).
 *
 *  - Probation : cuti tahunan diblokir, hanya izin sakit / tanpa gaji.
 *  - PKWT      : kuota cuti tahunan aktif sesuai employment_types.
 *  - Mitra     : tidak ada kuota cuti; hanya pencatatan ketidakhadiran.
 */
class LeavePolicyService
{
    public const LEAVE_TYPES = ['annual', 'sick', 'unpaid', 'maternity', 'special'];

    public const LEAVE_LABELS = [
        'annual' => 'Cuti Tahunan',
        'sick' => 'Izin Sakit',
        'unpaid' => 'Izin Tanpa Gaji',
        'maternity' => 'Cuti Melahirkan',
        'special' => 'Cuti Khusus',
    ];

    /** Jenis yang tetap boleh diajukan meski entitas tidak berhak cuti tahunan. */
    private const TYPES_WITHOUT_QUOTA = ['sick', 'unpaid'];

    /**
     * Alasan penolakan, atau null bila pengajuan boleh lanjut.
     */
    public function validateRequest(Employee $employee, string $leaveType): ?string
    {
        if ($employee->isLeaveEligible()) {
            return null;
        }

        if (in_array($leaveType, self::TYPES_WITHOUT_QUOTA, true)) {
            return null;
        }

        $entity = $employee->employmentType?->name ?? 'entitas ini';

        return "Entitas kerja {$entity} tidak memiliki hak {$this->label($leaveType)}. "
            .'Yang tersedia hanya izin sakit dan izin tanpa gaji.';
    }

    /**
     * Pesan yang ditampilkan di portal ketika hak cuti tahunan tidak aktif.
     */
    public function blockedReason(Employee $employee): ?string
    {
        if ($employee->isLeaveEligible()) {
            return null;
        }

        $entity = $employee->employmentType?->name ?? 'entitas kerja Anda';

        // Hak cuti adalah konfigurasi per entitas (halaman Entitas Kerja),
        // bukan sifat bawaan kategori — pesannya dibuat netral agar tetap
        // benar apa pun kebijakan yang sedang berlaku.
        return "Kuota cuti tahunan untuk entitas {$entity} sedang tidak aktif. "
            .'Anda tetap dapat mengajukan izin sakit atau izin tanpa gaji.';
    }

    /**
     * @return list<string>
     */
    public function allowedTypes(Employee $employee): array
    {
        return $employee->isLeaveEligible()
            ? self::LEAVE_TYPES
            : self::TYPES_WITHOUT_QUOTA;
    }

    /**
     * Saldo cuti tahunan berjalan.
     *
     * @return array{quota: int, used: int, remaining: int}
     */
    public function balance(Employee $employee): array
    {
        $quota = $employee->isLeaveEligible()
            ? (int) ($employee->employmentType?->annual_leave_quota ?? 0)
            : 0;

        $used = (int) $employee->leaveRequests()
            ->where('leave_type', 'annual')
            ->where('status', 'approved')
            ->whereYear('start_date', CarbonImmutable::now()->year)
            ->sum('total_days');

        return [
            'quota' => $quota,
            'used' => $used,
            'remaining' => max($quota - $used, 0),
        ];
    }

    private function label(string $leaveType): string
    {
        return self::LEAVE_LABELS[$leaveType] ?? $leaveType;
    }
}
