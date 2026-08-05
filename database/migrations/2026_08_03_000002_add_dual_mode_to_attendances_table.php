<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi dua opsi:
 *  - live  : kamera langsung + wajib berada di dalam radius kantor (perilaku lama)
 *  - upload: unggah foto dari perangkat + titik GPS, boleh di luar radius,
 *            tetapi wajib diverifikasi HR sebelum diakui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('clock_in_method', ['live', 'upload'])
                ->default('live')
                ->after('clock_in_photo');
            $table->unsignedInteger('clock_in_distance')->nullable()->after('clock_in_method');
            $table->string('clock_in_office')->nullable()->after('clock_in_distance');
            $table->boolean('is_outside_radius')->default(false)->after('clock_in_office');
            $table->string('clock_in_note', 500)->nullable()->after('is_outside_radius');
            $table->enum('verification_status', ['auto', 'pending', 'approved', 'rejected'])
                ->default('auto')
                ->after('clock_in_note');
            $table->foreignId('verified_by')->nullable()->after('verification_status')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->string('verification_note', 500)->nullable()->after('verified_at');

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'clock_in_method',
                'clock_in_distance',
                'clock_in_office',
                'is_outside_radius',
                'clock_in_note',
                'verification_status',
                'verified_at',
                'verification_note',
            ]);
        });
    }
};
