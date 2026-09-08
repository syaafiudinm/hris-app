<?php

/**
 * Provisioning akun login karyawan.
 *
 * Akun dibuat otomatis saat HR menambah karyawan ber-email, dan `users.email`
 * unik di level database. Dua hal yang diuji di sini adalah dua cara alur itu
 * pernah bisa gagal buruk:
 *
 *   1. email kembar → QueryException 500 setelah karyawan terlanjur tersimpan;
 *   2. password awal seragam → setiap akun baru dapat ditebak siapa pun yang
 *      pernah menerima akun dari sistem ini.
 */

use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\User;
use App\Services\AccountProvisioningService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'HR Test',
        'email' => 'hr@test.local',
        'password' => Hash::make('rahasia123'),
        'role' => 'super_admin',
        'must_change_password' => false,
    ]);

    $this->pkwt = EmploymentType::create([
        'code' => 'PKWT12-ACC',
        'name' => 'PKWT 12 Bulan',
        'category' => 'pkwt',
        'duration_months' => 12,
        'is_leave_eligible' => true,
        'is_bpjs_eligible' => true,
        'annual_leave_quota' => 12,
    ]);
});

/**
 * Payload form karyawan yang valid.
 *
 * @return array<string, mixed>
 */
function formKaryawan(EmploymentType $type, array $ubah = []): array
{
    return array_merge([
        'nik' => 'ACC-0001',
        'full_name' => 'Budi Santoso',
        'email' => 'budi@test.local',
        'phone' => '08123456789',
        'position' => 'Staf',
        'ptkp_status' => 'TK/0',
        'employment_type_id' => $type->id,
        'department_id' => null,
        'join_date' => '2026-09-01',
        'contract_start' => '2026-09-01',
        'contract_end' => '2027-09-01',
        'basic_salary' => 8_000_000,
        'status' => 'active',
    ], $ubah);
}

/* ------------------------------------------------------- Email kembar */

test('email yang sudah dipakai karyawan lain ditolak validasi', function () {
    $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));

    $response = $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt, [
        'nik' => 'ACC-0002',
        'full_name' => 'Budi Kembar',
    ]));

    $response->assertSessionHasErrors('email');
    expect(Employee::count())->toBe(1);
});

test('karyawan tidak tersimpan bila pembuatan akunnya gagal', function () {
    // Email sudah terpakai user lain yang bukan karyawan — lolos unique
    // employees, tapi dibentur users.email. Transaksi harus membatalkan
    // baris karyawan, bukan meninggalkannya tanpa akun.
    User::create([
        'name' => 'Akun Lama',
        'email' => 'budi@test.local',
        'password' => Hash::make('rahasia123'),
        'role' => 'employee',
    ]);

    $response = $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));

    $response->assertSessionHasErrors('email');
    expect(Employee::count())->toBe(0);
});

test('email boleh dipakai ulang oleh karyawan yang sama saat diubah', function () {
    $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));
    $karyawan = Employee::first();

    $this->actingAs($this->admin)
        ->put("/employees/{$karyawan->id}", formKaryawan($this->pkwt, [
            'full_name' => 'Budi Santoso Revisi',
        ]))
        ->assertSessionHasNoErrors();

    expect($karyawan->fresh()->full_name)->toBe('Budi Santoso Revisi');
});

test('mengubah email karyawan ikut memindahkan email login', function () {
    $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));
    $karyawan = Employee::first();

    $this->actingAs($this->admin)->put("/employees/{$karyawan->id}", formKaryawan($this->pkwt, [
        'email' => 'budi.baru@test.local',
    ]));

    // Tanpa sinkronisasi ini karyawan akan login dengan alamat yang berbeda
    // dari yang tertera di profilnya sendiri.
    expect($karyawan->fresh()->user->email)->toBe('budi.baru@test.local');
});

/* ---------------------------------------------------- Password bangkitan */

test('password awal dibangkitkan acak, bukan nilai default yang seragam', function () {
    $service = app(AccountProvisioningService::class);

    $satu = Employee::create([
        'employment_type_id' => $this->pkwt->id,
        'nik' => 'ACC-1001',
        'full_name' => 'Karyawan Satu',
        'email' => 'satu@test.local',
        'join_date' => '2026-09-01',
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    $dua = Employee::create([
        'employment_type_id' => $this->pkwt->id,
        'nik' => 'ACC-1002',
        'full_name' => 'Karyawan Dua',
        'email' => 'dua@test.local',
        'join_date' => '2026-09-01',
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    $passwordSatu = $service->provision($satu)['generated_password'];
    $passwordDua = $service->provision($dua)['generated_password'];

    expect($passwordSatu)->not->toBe($passwordDua)
        ->and($passwordSatu)->not->toBe('password')
        ->and(strlen($passwordSatu))->toBe(12)
        ->and(Hash::check($passwordSatu, $satu->fresh()->user->password))->toBeTrue();
});

test('password bangkitan menghindari karakter yang rancu bila didiktekan', function () {
    $service = app(AccountProvisioningService::class);

    $karyawan = Employee::create([
        'employment_type_id' => $this->pkwt->id,
        'nik' => 'ACC-1003',
        'full_name' => 'Karyawan Tiga',
        'email' => 'tiga@test.local',
        'join_date' => '2026-09-01',
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    // Diperiksa berulang karena karakter terlarang hanya muncul acak.
    for ($i = 0; $i < 25; $i++) {
        $password = $service->provision($karyawan)['generated_password'];

        expect($password)->not->toMatch('/[0O1lI]/');
    }
});

test('akun baru dan akun yang direset sama-sama wajib ganti password', function () {
    $service = app(AccountProvisioningService::class);

    $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));
    $karyawan = Employee::first();

    expect($karyawan->user->must_change_password)->toBeTrue();

    $karyawan->user->update(['must_change_password' => false]);

    $passwordBaru = $service->resetPassword($karyawan->fresh());

    expect($karyawan->fresh()->user->must_change_password)->toBeTrue()
        ->and(strlen($passwordBaru))->toBe(12)
        ->and(Hash::check($passwordBaru, $karyawan->fresh()->user->password))->toBeTrue();
});

test('password awal ditampilkan sekali kepada HR lewat flash message', function () {
    $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));

    $pesan = session('success');

    // Password ada di database hanya sebagai hash, jadi flash inilah satu-
    // satunya kesempatan HR mencatatnya — pesannya harus mengatakan itu.
    expect($pesan)->toContain('Akun login dibuat dengan password:')
        ->and($pesan)->toContain('tidak ditampilkan lagi');

    $karyawan = Employee::first();
    preg_match('/password: (\S+)/', $pesan, $cocok);

    expect(Hash::check($cocok[1], $karyawan->user->password))->toBeTrue();
});

/* ------------------------------------------------------- Cabut & hapus */

test('mencabut akun menghapus user dan melepas tautannya', function () {
    $this->actingAs($this->admin)->post('/employees', formKaryawan($this->pkwt));
    $karyawan = Employee::first();
    $userId = $karyawan->user_id;

    $this->actingAs($this->admin)->delete("/employees/{$karyawan->id}/akun");

    expect($karyawan->fresh()->user_id)->toBeNull()
        ->and(User::find($userId))->toBeNull();
});

test('karyawan tanpa email tetap tersimpan, hanya tanpa akun login', function () {
    $this->actingAs($this->admin)
        ->post('/employees', formKaryawan($this->pkwt, ['email' => null]))
        ->assertSessionHasNoErrors();

    $karyawan = Employee::first();

    expect($karyawan)->not->toBeNull()
        ->and($karyawan->user_id)->toBeNull();
});
