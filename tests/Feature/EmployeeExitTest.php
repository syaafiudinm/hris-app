<?php

use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\User;
use App\Services\ExitService;
use Database\Seeders\HrisDemoSeeder;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->first();
});

function karyawanTanpaExit(): Employee
{
    return Employee::active()
        ->whereNull('user_id')
        ->whereDoesntHave('exit')
        ->firstOrFail();
}

test('mencatat proses keluar tidak langsung menonaktifkan karyawan', function () {
    $employee = karyawanTanpaExit();

    $this->actingAs($this->admin)->post('/proses-keluar', [
        'employee_id' => $employee->id,
        'exit_type' => 'resign',
        'last_working_date' => now()->addDays(14)->toDateString(),
        'reason' => 'Pindah kota',
    ])->assertRedirect();

    $exit = EmployeeExit::where('employee_id', $employee->id)->firstOrFail();

    expect($exit->status)->toBe('draft')
        // Masih aktif sampai prosesnya dituntaskan.
        ->and($employee->fresh()->status)->toBe('active')
        ->and($exit->paklaring_number)->toBeNull();
});

test('menuntaskan proses keluar mengubah status karyawan sesuai jenisnya', function () {
    $kasus = [
        'resign' => 'resigned',
        'contract_end' => 'expired',
        'termination' => 'resigned',
        'retirement' => 'resigned',
    ];

    foreach ($kasus as $jenis => $statusHarapan) {
        $employee = karyawanTanpaExit();

        $exit = EmployeeExit::create([
            'employee_id' => $employee->id,
            'exit_type' => $jenis,
            'last_working_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->patch("/proses-keluar/{$exit->id}/status", ['status' => 'completed'])
            ->assertRedirect();

        expect($employee->fresh()->status)->toBe($statusHarapan, "jenis {$jenis}");
    }
});

test('paklaring hanya terbit setelah proses dituntaskan', function () {
    $employee = karyawanTanpaExit();

    $exit = EmployeeExit::create([
        'employee_id' => $employee->id,
        'exit_type' => 'resign',
        'last_working_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    // Draft: ditolak.
    $this->actingAs($this->admin)
        ->get("/proses-keluar/{$exit->id}/paklaring")
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->patch("/proses-keluar/{$exit->id}/status", ['status' => 'completed']);

    $response = $this->actingAs($this->admin)->get("/proses-keluar/{$exit->id}/paklaring");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('nomor paklaring tidak berubah saat dicetak ulang', function () {
    $exit = EmployeeExit::whereNotNull('paklaring_number')->firstOrFail();
    $nomorAwal = $exit->paklaring_number;

    // Cetak ulang beberapa kali — mantan karyawan sering meminta lagi.
    foreach (range(1, 3) as $ignored) {
        $this->actingAs($this->admin)
            ->get("/proses-keluar/{$exit->id}/paklaring")
            ->assertOk();
    }

    expect($exit->fresh()->paklaring_number)->toBe($nomorAwal);
});

test('membuka kembali proses keluar mengaktifkan karyawan tanpa menghapus nomor surat', function () {
    $exit = EmployeeExit::where('status', 'completed')->firstOrFail();
    $nomor = $exit->paklaring_number;

    $this->actingAs($this->admin)
        ->patch("/proses-keluar/{$exit->id}/status", ['status' => 'draft'])
        ->assertRedirect();

    $exit->refresh();

    expect($exit->status)->toBe('draft')
        ->and($exit->employee->status)->toBe('active')
        // Nomor dipertahankan agar surat yang terlanjur beredar tetap terlacak.
        ->and($exit->paklaring_number)->toBe($nomor);
});

test('satu karyawan hanya boleh punya satu catatan keluar', function () {
    $exit = EmployeeExit::first();

    $this->actingAs($this->admin)->post('/proses-keluar', [
        'employee_id' => $exit->employee_id,
        'exit_type' => 'resign',
        'last_working_date' => now()->toDateString(),
    ])->assertSessionHas('error');

    expect(EmployeeExit::where('employee_id', $exit->employee_id)->count())->toBe(1);
});

test('proses yang sudah tuntas tidak dapat dihapus', function () {
    $exit = EmployeeExit::where('status', 'completed')->firstOrFail();

    $this->actingAs($this->admin)
        ->delete("/proses-keluar/{$exit->id}")
        ->assertSessionHas('error');

    $this->assertDatabaseHas('employee_exits', ['id' => $exit->id]);
});

test('nomor paklaring berurutan dan unik', function () {
    $service = app(ExitService::class);
    $nomor = [];

    foreach (range(1, 3) as $ignored) {
        $employee = karyawanTanpaExit();

        $exit = EmployeeExit::create([
            'employee_id' => $employee->id,
            'exit_type' => 'resign',
            'last_working_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $nomor[] = $service->complete($exit)->paklaring_number;
    }

    expect($nomor)->toHaveCount(3)
        ->and(array_unique($nomor))->toHaveCount(3);
});

test('masa kerja dihitung dari tanggal bergabung sampai hari kerja terakhir', function () {
    $employee = karyawanTanpaExit();
    $employee->update(['join_date' => '2024-01-15']);

    $exit = EmployeeExit::create([
        'employee_id' => $employee->id,
        'exit_type' => 'resign',
        'last_working_date' => '2026-04-15',
        'status' => 'draft',
    ]);

    $tenure = $exit->fresh()->tenure();

    expect($tenure['years'])->toBe(2)
        ->and($tenure['months'])->toBe(3)
        ->and($tenure['label'])->toBe('2 tahun 3 bulan');
});

test('hanya super admin yang dapat mengakses proses keluar', function () {
    foreach (['manager@perusahaan.co.id', 'karyawan@perusahaan.co.id'] as $email) {
        $user = User::where('email', $email)->firstOrFail();

        $this->actingAs($user)->get('/proses-keluar')->assertForbidden();
        $this->actingAs($user)->post('/proses-keluar', [
            'employee_id' => karyawanTanpaExit()->id,
            'exit_type' => 'resign',
            'last_working_date' => now()->toDateString(),
        ])->assertForbidden();
    }
});
