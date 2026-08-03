<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insentif penjualan mitra dibayarkan lewat slip tersendiri, terpisah dari
 * slip gaji. Satu periode karena itu dapat menghasilkan dua slip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->enum('slip_type', ['salary', 'incentive'])
                ->default('salary')
                ->after('payout_type');
        });

        // Index baru dibuat lebih dulu. Kolom pertamanya tetap employee_id,
        // sehingga foreign key punya index pengganti sebelum index lama
        // dilepas — MySQL menolak bila urutannya dibalik.
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'period_year', 'period_month', 'slip_type'],
                'payrolls_employee_period_slip_unique',
            );
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique('payrolls_employee_period_slip_unique');
            $table->unique(['employee_id', 'period_year', 'period_month']);
            $table->dropColumn('slip_type');
        });
    }
};
