<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema kompensasi penjualan untuk Mitra: katalog produk beserta insentif
 * per unit, dan catatan unit terjual per mitra per periode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('incentive_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('quantity')->default(0);
            $table->timestamps();

            // Satu baris per mitra-produk-periode; kuantitas diakumulasi di sana.
            $table->unique(['employee_id', 'sales_product_id', 'period_year', 'period_month'], 'sales_records_unique_period');
            $table->index(['period_year', 'period_month']);
        });

        // Enum dilepas menjadi string agar tipe skema baru (mis. "sales")
        // dapat ditambah tanpa ALTER enum yang berbeda dialek MySQL/SQLite.
        // Validasi tetap dijaga di level aplikasi.
        Schema::table('mitra_payroll_schemas', function (Blueprint $table) {
            $table->string('schema_type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_records');
        Schema::dropIfExists('sales_products');
    }
};
