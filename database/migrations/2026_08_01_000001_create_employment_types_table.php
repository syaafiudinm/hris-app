<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('category', ['probation', 'pkwt', 'mitra']);
            $table->unsignedTinyInteger('duration_months')->nullable();
            $table->boolean('is_leave_eligible')->default(false);
            $table->boolean('is_bpjs_eligible')->default(false);
            $table->unsignedTinyInteger('annual_leave_quota')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_types');
    }
};
