<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Divisi/departemen sebenarnya. Belum ada UI pengelolaannya, jadi daftar ini
 * adalah sumber kebenaran — ditanam lewat migrasi supaya server produksi
 * langsung memilikinya tanpa perlu menjalankan seeder demo.
 */
return new class extends Migration
{
    private const DEPARTMENTS = [
        ['code' => 'SALES', 'name' => 'Sales'],
        ['code' => 'AFTERSALES', 'name' => 'Aftersales'],
        ['code' => 'HRGA', 'name' => 'HRGA'],
        ['code' => 'FAT', 'name' => 'FAT'],
        ['code' => 'MKT', 'name' => 'Marketing'],
        ['code' => 'OPS', 'name' => 'Operasional'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::DEPARTMENTS as $department) {
            DB::table('departments')->updateOrInsert(
                ['code' => $department['code']],
                [
                    'name' => $department['name'],
                    'location' => 'Makassar',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        // Divisi mungkin sudah dirujuk karyawan, lowongan, dan dokumen;
        // menghapusnya saat rollback akan memutus relasi tersebut.
    }
};
