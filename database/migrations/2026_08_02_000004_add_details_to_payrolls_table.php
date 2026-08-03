<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Rincian komponen yang bentuknya berbeda per skema — mis. uang
            // makan, insentif, dan bonus tier pada skema penjualan mitra.
            // Disimpan agar voucher dapat mencetak angka yang sama persis
            // dengan saat payroll dijalankan.
            $table->json('details')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
