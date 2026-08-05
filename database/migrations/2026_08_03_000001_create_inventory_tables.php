<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('category', 40)->default('lainnya');
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();
            // Jumlah unit yang dimiliki. Aset serial (laptop, kendaraan)
            // umumnya 1; barang generik (headset, modem) bisa lebih.
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->enum('condition', ['good', 'minor', 'damaged'])->default('good');
            $table->enum('status', ['active', 'maintenance', 'retired'])->default('active');
            $table->string('location')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });

        Schema::create('inventory_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->enum('status', [
                'requested',  // diajukan pegawai, menunggu HR
                'approved',   // disetujui, barang belum diserahkan
                'borrowed',   // barang sudah dipegang peminjam
                'returned',   // selesai
                'rejected',   // ditolak HR
                'lost',       // hilang/rusak total, masuk klaim
            ])->default('requested');
            $table->text('purpose');
            $table->date('due_date');
            $table->dateTime('handed_over_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->enum('condition_out', ['good', 'minor', 'damaged'])->nullable();
            $table->enum('condition_in', ['good', 'minor', 'damaged'])->nullable();
            $table->text('decision_note')->nullable();
            $table->text('return_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['inventory_item_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_loans');
        Schema::dropIfExists('inventory_items');
    }
};
