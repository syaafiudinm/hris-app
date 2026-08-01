<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OfficeLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Absensi presisi digital: geofencing, verifikasi foto, dan deteksi
 * mock location (Masterplan §2.2).
 */
class AttendanceService
{
    /** Batas jam masuk sebelum dihitung terlambat. */
    public const WORK_START_HOUR = 8;
    public const WORK_START_MINUTE = 0;

    /** Kecepatan perpindahan wajar antar-absen (km/jam). */
    private const MAX_PLAUSIBLE_SPEED_KMH = 200;

    /**
     * Cari kantor terdekat dan tentukan apakah titik berada di dalam radius.
     *
     * @return array{location: ?OfficeLocation, distance: ?float, inside: bool}
     */
    public function resolveGeofence(float $latitude, float $longitude): array
    {
        $nearest = null;
        $shortest = null;

        foreach (OfficeLocation::where('is_active', true)->get() as $location) {
            $distance = $location->distanceTo($latitude, $longitude);

            if ($shortest === null || $distance < $shortest) {
                $shortest = $distance;
                $nearest = $location;
            }
        }

        return [
            'location' => $nearest,
            'distance' => $shortest,
            'inside' => $nearest !== null && $shortest <= $nearest->radius_meters,
        ];
    }

    /**
     * Deteksi indikasi lokasi palsu. Mengembalikan daftar alasan — kosong
     * berarti tidak ada indikasi.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function detectFakeGps(Employee $employee, array $payload): array
    {
        $reasons = [];

        // 1. Perangkat melaporkan mock location provider secara eksplisit.
        if (! empty($payload['is_mock_location'])) {
            $reasons[] = 'Perangkat melaporkan mock location provider aktif.';
        }

        // 2. Akurasi yang mustahil — GPS asli tidak pernah presisi sempurna.
        $accuracy = isset($payload['accuracy']) ? (float) $payload['accuracy'] : null;
        if ($accuracy !== null && $accuracy > 0 && $accuracy < 1) {
            $reasons[] = 'Akurasi GPS tidak wajar (< 1 meter).';
        }

        // 3. Koordinat terlalu bulat — ciri khas titik yang diketik manual.
        if ($this->isSuspiciouslyRound((float) $payload['latitude'])
            && $this->isSuspiciouslyRound((float) $payload['longitude'])) {
            $reasons[] = 'Koordinat terlalu bulat, indikasi input manual.';
        }

        // 4. Perpindahan mustahil dibanding absensi terakhir.
        if ($this->hasImpossibleTravel($employee, (float) $payload['latitude'], (float) $payload['longitude'])) {
            $reasons[] = 'Perpindahan lokasi tidak masuk akal dari absensi sebelumnya.';
        }

        return $reasons;
    }

    /**
     * Simpan foto selfie (data URL base64) ke storage publik.
     */
    public function storePhoto(string $dataUrl, Employee $employee): ?string
    {
        if (! preg_match('/^data:image\/(jpe?g|png|webp);base64,/', $dataUrl, $matches)) {
            return null;
        }

        $extension = $matches[1] === 'jpg' ? 'jpeg' : $matches[1];
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false) {
            return null;
        }

        $path = sprintf('attendance/%s/%s.%s', $employee->id, Str::uuid(), $extension);
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Status kehadiran berdasar jam masuk.
     *
     * @return array{status: string, lateMinutes: int}
     */
    public function evaluateClockIn(CarbonImmutable $clockIn): array
    {
        $threshold = $clockIn->setTime(self::WORK_START_HOUR, self::WORK_START_MINUTE);
        $lateMinutes = $clockIn->greaterThan($threshold)
            ? (int) $threshold->diffInMinutes($clockIn)
            : 0;

        return [
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'lateMinutes' => $lateMinutes,
        ];
    }

    private function isSuspiciouslyRound(float $coordinate): bool
    {
        // Empat desimal ~ 11 meter; nol semua di belakang koma sangat tidak lazim.
        return abs($coordinate - round($coordinate, 2)) < 1e-9;
    }

    private function hasImpossibleTravel(Employee $employee, float $latitude, float $longitude): bool
    {
        $previous = Attendance::where('employee_id', $employee->id)
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_in_lat')
            ->latest('clock_in')
            ->first();

        if (! $previous) {
            return false;
        }

        $hours = $previous->clock_in->diffInSeconds(now()) / 3600;

        if ($hours <= 0.01) {
            return false;
        }

        $km = $this->haversineKm(
            (float) $previous->clock_in_lat,
            (float) $previous->clock_in_long,
            $latitude,
            $longitude,
        );

        return ($km / $hours) > self::MAX_PLAUSIBLE_SPEED_KMH;
    }

    private function haversineKm(float $lat1, float $long1, float $lat2, float $long2): float
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLong = deg2rad($long2 - $long1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLong / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
