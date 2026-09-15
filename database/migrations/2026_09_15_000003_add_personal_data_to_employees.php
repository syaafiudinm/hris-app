<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data diri yang diisi sendiri oleh pemilik akun.
 *
 * Seluruhnya nullable: karyawan dibuat HR lebih dulu, lalu datanya
 * dilengkapi karyawan lewat halaman Data Diri. `nik` yang sudah ada adalah
 * nomor induk karyawan, bukan nomor KTP — keduanya sengaja dipisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ktp_number', 16)->nullable()->unique()->after('full_name');
            $table->string('birth_place', 100)->nullable()->after('ktp_number');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('gender', 10)->nullable()->after('birth_date');
            $table->string('religion', 20)->nullable()->after('gender');
            $table->string('marital_status', 20)->nullable()->after('religion');
            $table->unsignedTinyInteger('dependents_count')->nullable()->after('marital_status');
            $table->text('ktp_address')->nullable()->after('dependents_count');
            $table->text('domicile_address')->nullable()->after('ktp_address');
            $table->string('emergency_contact_name', 150)->nullable()->after('phone');
            $table->string('emergency_contact_relation', 50)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_relation');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['ktp_number']);
            $table->dropColumn([
                'ktp_number', 'birth_place', 'birth_date', 'gender', 'religion',
                'marital_status', 'dependents_count', 'ktp_address', 'domicile_address',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            ]);
        });
    }
};
