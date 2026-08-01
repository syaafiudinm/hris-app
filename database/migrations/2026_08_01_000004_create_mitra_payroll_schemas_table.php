<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_payroll_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('schema_type', ['fixed_project', 'hourly', 'daily', 'milestone', 'unit']);
            $table->decimal('rate_per_unit', 15, 2)->default(0);
            $table->string('unit_label')->nullable();
            $table->enum('tax_scheme', ['pph21_berkesinambungan', 'pph21_tidak_berkesinambungan', 'pph23', 'bebas_pajak'])
                ->default('pph21_tidak_berkesinambungan');
            $table->decimal('custom_tax_percentage', 5, 2)->default(0);
            // Komponen custom (bonus, penalty, milestone list) disimpan sebagai JSON
            // agar dapat berubah tanpa migrasi struktur tabel.
            $table->json('components')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_payroll_schemas');
    }
};
