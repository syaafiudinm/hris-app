<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->enum('offered_category', ['probation', 'pkwt', 'mitra']);
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('quota')->default(1);
            $table->enum('status', ['open', 'closed', 'draft'])->default('open');
            $table->date('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_vacancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('converted_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('cv_path')->nullable();
            $table->enum('stage', ['applied', 'screening', 'interview', 'offering', 'hired', 'rejected'])
                ->default('applied');
            $table->timestamps();

            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('job_vacancies');
    }
};
