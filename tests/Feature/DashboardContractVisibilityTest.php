<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\HrisDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);

    // Hanya dua pegawai ini yang kontraknya berakhir dalam 30 hari.
    Employee::query()->update(['contract_end' => now()->addYear()->toDateString()]);

    [$this->pegawai, $this->rekan] = Employee::active()
        ->whereHas('user', fn ($query) => $query->where('role', 'employee'))
        ->limit(2)
        ->get()
        ->all();

    $this->pegawai->update(['contract_end' => now()->addDays(10)->toDateString()]);
    $this->rekan->update(['contract_end' => now()->addDays(20)->toDateString()]);
});

test('admin melihat status kadaluwarsa kontrak semua pegawai', function () {
    $admin = User::where('role', 'super_admin')->firstOrFail();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewAllContracts', true)
            ->where('summary.expiringCount', 2)
            ->has('expiringContracts', 2));
});

test('pegawai hanya melihat status kadaluwarsa kontraknya sendiri', function () {
    $this->actingAs($this->pegawai->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewAllContracts', false)
            ->where('summary.expiringCount', 1)
            ->has('expiringContracts', 1)
            ->where('expiringContracts.0.id', $this->pegawai->id));
});

test('manager juga hanya melihat kontraknya sendiri', function () {
    $manager = User::where('role', 'manager')->whereHas('employee')->firstOrFail();

    $this->actingAs($manager)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewAllContracts', false)
            ->where('summary.expiringCount', 0)
            ->has('expiringContracts', 0));
});
