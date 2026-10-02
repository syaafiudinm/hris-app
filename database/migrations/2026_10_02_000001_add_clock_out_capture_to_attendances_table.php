<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clock-out kini melewati alur yang sama dengan clock-in: titik GPS dan
 * foto, lewat kamera langsung (wajib di dalam radius) atau unggah foto
 * (boleh di luar radius, diverifikasi HR).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('verification_note');
            $table->decimal('clock_out_long', 10, 7)->nullable()->after('clock_out_lat');
            $table->string('clock_out_photo')->nullable()->after('clock_out_long');
            $table->enum('clock_out_method', ['live', 'upload'])->nullable()->after('clock_out_photo');
            $table->unsignedInteger('clock_out_distance')->nullable()->after('clock_out_method');
            $table->string('clock_out_office')->nullable()->after('clock_out_distance');
            $table->boolean('is_clock_out_outside_radius')->default(false)->after('clock_out_office');
            $table->string('clock_out_note', 500)->nullable()->after('is_clock_out_outside_radius');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_out_lat',
                'clock_out_long',
                'clock_out_photo',
                'clock_out_method',
                'clock_out_distance',
                'clock_out_office',
                'is_clock_out_outside_radius',
                'clock_out_note',
            ]);
        });
    }
};
