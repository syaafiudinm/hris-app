<?php

use App\Support\TerTariff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status PTKP menentukan kategori TER (A/B/C) pada perhitungan PPh 21.
 *
 * Default TK/0 dipilih karena itu kategori dengan PTKP terendah: karyawan
 * lama yang belum diperbarui statusnya akan terpotong sedikit lebih besar,
 * bukan kurang potong — kelebihan bisa direstitusi lewat SPT, kekurangan
 * jadi utang pajak karyawan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ptkp_status', 10)
                ->default(TerTariff::DEFAULT_PTKP)
                ->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('ptkp_status');
        });
    }
};
