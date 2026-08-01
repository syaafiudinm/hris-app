<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->json('notes')->nullable()->after('stage');
            $table->json('stage_history')->nullable()->after('notes');
            $table->timestamp('stage_changed_at')->nullable()->after('stage_history');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['notes', 'stage_history', 'stage_changed_at']);
        });
    }
};
