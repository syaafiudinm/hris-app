<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 5 — Knowledge Center: Bulletin Pengumuman & Storage SOP/Peraturan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('category', ['info', 'policy', 'urgent'])->default('info');

            // Penargetan audiens: seluruh perusahaan, satu divisi,
            // atau satu kategori entitas kerja.
            $table->enum('target_type', ['all', 'department', 'employment_category'])
                ->default('all');
            $table->foreignId('target_department_id')->nullable()
                ->constrained('departments')->nullOnDelete();
            $table->enum('target_category', ['probation', 'pkwt', 'mitra'])->nullable();

            $table->boolean('is_pinned')->default(false);
            // Null berarti draft — belum terbit bagi karyawan.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['published_at', 'is_pinned']);
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('doc_type', ['sop', 'peraturan', 'panduan', 'formulir'])
                ->default('sop');

            // Berkas disimpan di disk privat; unduhan lewat route ber-RBAC.
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->string('version', 20)->default('1.0');

            $table->enum('target_type', ['all', 'department', 'employment_category'])
                ->default('all');
            $table->foreignId('target_department_id')->nullable()
                ->constrained('departments')->nullOnDelete();
            $table->enum('target_category', ['probation', 'pkwt', 'mitra'])->nullable();

            $table->unsignedInteger('download_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['doc_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('announcements');
    }
};
