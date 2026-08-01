<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeLocation extends Model
{
    protected $fillable = ['name', 'latitude', 'longitude', 'radius_meters', 'is_active'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Jarak haversine (meter) dari titik absen ke lokasi kantor.
     */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $earthRadius = 6371000;

        $deltaLat = deg2rad($latitude - $this->latitude);
        $deltaLong = deg2rad($longitude - $this->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($deltaLong / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
