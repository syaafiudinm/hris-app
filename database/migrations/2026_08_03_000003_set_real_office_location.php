<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ganti titik kantor demo (Jakarta & Bandung) dengan lokasi sebenarnya.
 *
 * Dijalankan sebagai migrasi, bukan seeder, supaya database yang sudah berisi
 * data produksi ikut terkoreksi tanpa perlu menjalankan ulang seeder demo.
 * Titik lama wajib dibuang: selama barisnya masih aktif, siapa pun yang berada
 * di dekat Monas atau Gedung Sate bisa lolos geofence absensi.
 */
return new class extends Migration
{
    private const OFFICE = [
        'name' => 'Kantor Geely Ricklean Makassar',
        'latitude' => -5.1553330,
        'longitude' => 119.4237384,
        'radius_meters' => 150,
    ];

    public function up(): void
    {
        DB::table('office_locations')
            ->whereIn('name', ['Kantor Pusat Jakarta', 'Kantor Bandung'])
            ->delete();

        $existing = DB::table('office_locations')
            ->where('name', self::OFFICE['name'])
            ->first();

        if ($existing) {
            DB::table('office_locations')
                ->where('id', $existing->id)
                ->update(self::OFFICE + ['is_active' => true, 'updated_at' => now()]);

            return;
        }

        DB::table('office_locations')->insert(self::OFFICE + [
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sengaja tidak dibalik. Mengembalikan koordinat demo akan membuat
        // seluruh absensi di kantor sebenarnya ditolak geofence.
    }
};
