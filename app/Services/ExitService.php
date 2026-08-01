<?php

namespace App\Services;

use App\Models\EmployeeExit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Proses offboarding: menuntaskan exit dan menerbitkan nomor paklaring.
 */
class ExitService
{
    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Status karyawan setelah exit dituntaskan, sesuai penyebabnya.
     */
    private const RESULTING_STATUS = [
        'resign' => 'resigned',
        'termination' => 'resigned',
        'retirement' => 'resigned',
        'contract_end' => 'expired',
    ];

    /**
     * Tuntaskan proses exit: kunci status karyawan dan terbitkan nomor surat.
     */
    public function complete(EmployeeExit $exit): EmployeeExit
    {
        return DB::transaction(function () use ($exit) {
            $exit->status = 'completed';

            // Nomor hanya diterbitkan sekali — cetak ulang bertahun-tahun
            // kemudian harus menghasilkan nomor yang sama.
            if (! $exit->paklaring_number) {
                $exit->paklaring_number = $this->generateNumber();
                $exit->paklaring_issued_at = now();
            }

            $exit->save();

            $exit->employee?->update([
                'status' => self::RESULTING_STATUS[$exit->exit_type] ?? 'resigned',
            ]);

            return $exit;
        });
    }

    /**
     * Kembalikan exit ke draft dan aktifkan lagi karyawannya.
     *
     * Nomor paklaring sengaja tidak dihapus supaya surat yang terlanjur
     * beredar tetap dapat ditelusuri.
     */
    public function reopen(EmployeeExit $exit): EmployeeExit
    {
        return DB::transaction(function () use ($exit) {
            $exit->update(['status' => 'draft']);
            $exit->employee?->update(['status' => 'active']);

            return $exit;
        });
    }

    /**
     * Nomor surat berformat 001/PKL-HR/VIII/2026, berurutan per tahun.
     */
    public function generateNumber(?CarbonImmutable $date = null): string
    {
        $date ??= CarbonImmutable::now();

        $issuedThisYear = EmployeeExit::whereNotNull('paklaring_number')
            ->whereYear('paklaring_issued_at', $date->year)
            ->count();

        for ($attempt = 1; $attempt <= 1000; $attempt++) {
            $candidate = sprintf(
                '%03d/PKL-HR/%s/%d',
                $issuedThisYear + $attempt,
                self::ROMAN_MONTHS[$date->month],
                $date->year,
            );

            if (! EmployeeExit::where('paklaring_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Sangat tidak mungkin tercapai; lebih baik gagal keras daripada
        // menerbitkan nomor duplikat.
        throw new \RuntimeException('Tidak dapat membuat nomor paklaring unik.');
    }
}
