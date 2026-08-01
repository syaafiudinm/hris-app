<?php

use App\Models\Applicant;
use App\Models\Department;
use App\Models\JobVacancy;
use App\Models\User;
use Database\Seeders\HrisDemoSeeder;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->first();
});

test('super admin dapat membuat lowongan baru', function () {
    $response = $this->actingAs($this->admin)->post('/lowongan', [
        'title' => 'Data Analyst',
        'offered_category' => 'pkwt',
        'department_id' => Department::first()->id,
        'location' => 'Jakarta',
        'description' => 'Mengolah data operasional.',
        'requirements' => 'SQL, Python',
        'quota' => 2,
        'status' => 'draft',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('job_vacancies', [
        'title' => 'Data Analyst',
        'status' => 'draft',
    ]);
});

test('lowongan draft belum tampil di portal karier, open tampil', function () {
    $vacancy = JobVacancy::create([
        'title' => 'Lowongan Rahasia',
        'offered_category' => 'pkwt',
        'quota' => 1,
        'status' => 'draft',
    ]);

    // Draft: tidak boleh diakses publik.
    $this->get("/karier/{$vacancy->id}")->assertNotFound();

    // Dipublikasikan lewat tombol status.
    $this->actingAs($this->admin)->patch("/lowongan/{$vacancy->id}/status", [
        'status' => 'open',
    ])->assertRedirect();

    $vacancy->refresh();

    expect($vacancy->status)->toBe('open')
        // Tanggal publikasi diisi otomatis saat pertama kali dibuka.
        ->and($vacancy->published_at)->not->toBeNull();

    $this->get("/karier/{$vacancy->id}")->assertOk();
});

test('lowongan yang sudah punya pelamar tidak dapat dihapus', function () {
    $vacancy = JobVacancy::has('applicants')->firstOrFail();

    $response = $this->actingAs($this->admin)->delete("/lowongan/{$vacancy->id}");

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('job_vacancies', ['id' => $vacancy->id]);
});

test('lowongan tanpa pelamar dapat dihapus', function () {
    $vacancy = JobVacancy::create([
        'title' => 'Lowongan Kosong',
        'offered_category' => 'mitra',
        'quota' => 1,
        'status' => 'draft',
    ]);

    $this->actingAs($this->admin)->delete("/lowongan/{$vacancy->id}")->assertRedirect();

    $this->assertDatabaseMissing('job_vacancies', ['id' => $vacancy->id]);
});

test('menutup lowongan menyembunyikannya dari portal tanpa menghapus pelamar', function () {
    $vacancy = JobVacancy::has('applicants')->where('status', 'open')->firstOrFail();
    $jumlahPelamar = Applicant::where('job_vacancy_id', $vacancy->id)->count();

    $this->actingAs($this->admin)->patch("/lowongan/{$vacancy->id}/status", [
        'status' => 'closed',
    ]);

    $this->get("/karier/{$vacancy->id}")->assertNotFound();

    expect(Applicant::where('job_vacancy_id', $vacancy->id)->count())
        ->toBe($jumlahPelamar);
});

test('hanya super admin yang dapat mengelola lowongan', function () {
    $manager = User::where('role', 'manager')->first();
    $karyawan = User::where('role', 'employee')->first();

    foreach ([$manager, $karyawan] as $user) {
        $this->actingAs($user)->get('/lowongan')->assertForbidden();
        $this->actingAs($user)->post('/lowongan', [
            'title' => 'Percobaan',
            'offered_category' => 'pkwt',
            'quota' => 1,
            'status' => 'open',
        ])->assertForbidden();
    }

    $this->assertDatabaseMissing('job_vacancies', ['title' => 'Percobaan']);
});
