<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_long', 10, 7)->nullable();
            $table->string('clock_in_photo')->nullable();
            $table->boolean('is_fake_gps')->default(false);
            $table->enum('status', ['present', 'late', 'absent', 'leave', 'holiday'])->default('present');
            $table->unsignedSmallInteger('work_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
