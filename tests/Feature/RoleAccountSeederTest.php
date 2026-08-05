<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleAccountSeeder;

test('seeder akun awal membuat satu pengguna per role beserta data karyawannya', function () {
    // Sengaja tanpa HrisDemoSeeder: seeder ini harus jalan di database kosong.
    $this->seed(RoleAccountSeeder::class);

    expect(User::whereIn('role', ['super_admin', 'manager', 'employee'])->count())->toBe(3);

    foreach (['hr@ricklean.co.id', 'manager@ricklean.co.id', 'staf@ricklean.co.id'] as $email) {
        $user = User::where('email', $email)->firstOrFail();

        // Tanpa Employee tertaut, seluruh portal mandiri membalas 403.
        expect($user->employee)->not->toBeNull()
            ->and($user->employee->status)->toBe('active');
    }
});

test('seeder akun awal aman dijalankan berulang', function () {
    $this->seed(RoleAccountSeeder::class);
    $this->seed(RoleAccountSeeder::class);

    expect(User::whereIn('email', ['hr@ricklean.co.id', 'manager@ricklean.co.id', 'staf@ricklean.co.id'])->count())->toBe(3)
        ->and(Employee::whereIn('nik', ['RCK-0001', 'RCK-0002', 'RCK-0003'])->count())->toBe(3);
});

test('tiap akun awal dapat membuka halaman sesuai rolenya', function () {
    $this->seed(RoleAccountSeeder::class);

    $hr = User::where('email', 'hr@ricklean.co.id')->firstOrFail();
    $manager = User::where('email', 'manager@ricklean.co.id')->firstOrFail();
    $staf = User::where('email', 'staf@ricklean.co.id')->firstOrFail();

    $this->actingAs($hr)->get('/dashboard')->assertOk();
    $this->actingAs($hr)->get('/inventaris')->assertOk();
    $this->actingAs($manager)->get('/absensi')->assertOk();
    $this->actingAs($staf)->get('/absensi-saya')->assertOk();
    $this->actingAs($staf)->get('/inventaris-saya')->assertOk();
    $this->actingAs($staf)->get('/inventaris')->assertForbidden();
});

test('kata sandi awal dapat dipakai login', function () {
    $this->seed(RoleAccountSeeder::class);

    $this->post('/login', [
        'email' => 'hr@ricklean.co.id',
        'password' => 'Ricklean#2026',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
});
