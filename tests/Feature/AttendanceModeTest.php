<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(AttendanceService::PHOTO_DISK);

    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->first();

    // Titik di dalam radius Kantor Geely Ricklean Makassar (radius 150 m).
    $this->diDalam = ['latitude' => -5.1553800, 'longitude' => 119.4238000];
    // Sekitar 5 km ke utara — jauh di luar radius mana pun.
    $this->diLuar = ['latitude' => -5.1112345, 'longitude' => 119.4256789];
});

/** Pegawai yang punya akun login dan belum absen hari ini. */
function pegawaiBelumAbsen(): Employee
{
    $employee = Employee::active()->whereNotNull('user_id')->firstOrFail();

    Attendance::where('employee_id', $employee->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    return $employee;
}

/** Data URL 1x1 piksel PNG — cukup untuk lolos validasi foto kamera. */
function fotoDataUrl(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

test('mode kamera langsung menolak clock-in dari luar radius kantor', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)
        ->post('/absensi-saya/clock-in', [
            'method' => 'live',
            ...$this->diLuar,
            'accuracy' => 12,
            'photo' => fotoDataUrl(),
        ])
        ->assertSessionHasErrors('latitude');

    expect(Attendance::where('employee_id', $employee->id)->whereDate('date', now())->exists())
        ->toBeFalse();
});

test('mode kamera langsung di dalam radius langsung sah tanpa verifikasi', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)
        ->post('/absensi-saya/clock-in', [
            'method' => 'live',
            ...$this->diDalam,
            'accuracy' => 8,
            'photo' => fotoDataUrl(),
        ])
        ->assertRedirect();

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    expect($record->clock_in_method)->toBe('live')
        ->and($record->verification_status)->toBe('auto')
        ->and($record->is_outside_radius)->toBeFalse()
        ->and($record->status)->toBeIn(['present', 'late']);
});

test('mode unggah foto diterima dari luar radius namun menunggu verifikasi', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)
        ->post('/absensi-saya/clock-in', [
            'method' => 'upload',
            ...$this->diLuar,
            'accuracy' => 20,
            'photo_file' => UploadedFile::fake()->image('absen.jpg'),
            'note' => 'Kunjungan klien di Bekasi',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    expect($record->clock_in_method)->toBe('upload')
        ->and($record->verification_status)->toBe('pending')
        ->and($record->is_outside_radius)->toBeTrue()
        ->and($record->clock_in_note)->toBe('Kunjungan klien di Bekasi')
        ->and($record->clock_in_distance)->toBeGreaterThan(200);

    Storage::disk(AttendanceService::PHOTO_DISK)->assertExists($record->clock_in_photo);
});

test('mode unggah wajib menyertakan berkas foto dan alasan', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)
        ->post('/absensi-saya/clock-in', [
            'method' => 'upload',
            ...$this->diDalam,
        ])
        ->assertSessionHasErrors(['photo_file', 'note']);
});

test('persetujuan HR mengembalikan hari itu menjadi kehadiran', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)->post('/absensi-saya/clock-in', [
        'method' => 'upload',
        ...$this->diLuar,
        'photo_file' => UploadedFile::fake()->image('absen.jpg'),
        'note' => 'Survey lokasi proyek',
    ]);

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    $this->actingAs($this->admin)
        ->patch("/absensi/{$record->id}/verifikasi", ['decision' => 'approved'])
        ->assertRedirect();

    $record->refresh();

    expect($record->verification_status)->toBe('approved')
        ->and($record->verified_by)->toBe($this->admin->id)
        ->and($record->status)->toBeIn(['present', 'late']);
});

test('penolakan HR mengubah hari itu menjadi tidak hadir', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)->post('/absensi-saya/clock-in', [
        'method' => 'upload',
        ...$this->diLuar,
        'photo_file' => UploadedFile::fake()->image('absen.jpg'),
        'note' => 'Lupa membawa ponsel kerja',
    ]);

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    $this->actingAs($this->admin)
        ->patch("/absensi/{$record->id}/verifikasi", [
            'decision' => 'rejected',
            'note' => 'Foto tidak menunjukkan lokasi kerja.',
        ])
        ->assertRedirect();

    $record->refresh();

    // Status "absent" membuat rekap dan payroll otomatis tidak menghitungnya.
    expect($record->verification_status)->toBe('rejected')
        ->and($record->status)->toBe('absent')
        ->and($record->late_minutes)->toBe(0)
        ->and($record->work_minutes)->toBe(0);
});

test('absensi yang sudah diputuskan tidak dapat diverifikasi ulang', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)->post('/absensi-saya/clock-in', [
        'method' => 'live',
        ...$this->diDalam,
        'photo' => fotoDataUrl(),
    ]);

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    $this->actingAs($this->admin)
        ->patch("/absensi/{$record->id}/verifikasi", ['decision' => 'rejected'])
        ->assertRedirect();

    // Mode live berstatus "auto", bukan "pending", jadi keputusan diabaikan.
    expect($record->fresh()->verification_status)->toBe('auto')
        ->and($record->fresh()->status)->not->toBe('absent');
});

test('foto absensi hanya dapat dilihat pemiliknya dan HR', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)->post('/absensi-saya/clock-in', [
        'method' => 'upload',
        ...$this->diDalam,
        'photo_file' => UploadedFile::fake()->image('absen.jpg'),
        'note' => 'Kamera browser bermasalah',
    ]);

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    $this->actingAs($employee->user)->get("/absensi/{$record->id}/foto")->assertOk();
    $this->actingAs($this->admin)->get("/absensi/{$record->id}/foto")->assertOk();
});

test('pegawai lain tidak dapat membuka foto absensi bukan miliknya', function () {
    $employee = pegawaiBelumAbsen();

    $this->actingAs($employee->user)->post('/absensi-saya/clock-in', [
        'method' => 'upload',
        ...$this->diDalam,
        'photo_file' => UploadedFile::fake()->image('absen.jpg'),
        'note' => 'Kamera browser bermasalah',
    ]);

    $record = Attendance::where('employee_id', $employee->id)->whereDate('date', now())->firstOrFail();

    $penyusup = User::where('role', 'employee')
        ->whereHas('employee', fn ($query) => $query->where('id', '!=', $employee->id))
        ->firstOrFail();

    $this->actingAs($penyusup)->get("/absensi/{$record->id}/foto")->assertForbidden();
});
