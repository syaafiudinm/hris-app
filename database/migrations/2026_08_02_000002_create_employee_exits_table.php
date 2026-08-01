<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proses offboarding & penerbitan paklaring (Modul 1 — Exit/Paklaring).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_exits', function (Blueprint $table) {
            $table->id();
            // Satu karyawan hanya punya satu catatan exit.
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();

            $table->enum('exit_type', ['resign', 'contract_end', 'termination', 'retirement']);
            $table->date('submitted_date')->nullable();
            $table->date('last_working_date');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            // draft = masih diproses; completed = resmi keluar dan
            // paklaring boleh diterbitkan.
            $table->enum('status', ['draft', 'completed'])->default('draft');

            // Nomor surat diterbitkan sekali lalu dipakai selamanya, supaya
            // paklaring yang dicetak ulang bertahun-tahun kemudian tetap sama.
            $table->string('paklaring_number')->nullable()->unique();
            $table->timestamp('paklaring_issued_at')->nullable();

            $table->foreignId('processed_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'last_working_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_exits');
    }
};
