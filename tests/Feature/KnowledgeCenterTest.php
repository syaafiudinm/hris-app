<?php

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->first();
    $this->staff = User::where('email', 'karyawan@perusahaan.co.id')->first();
    $this->mitra = User::where('email', 'mitra@perusahaan.co.id')->first();
});

test('semua role dapat membuka knowledge center', function () {
    foreach ([$this->admin, $this->staff, $this->mitra] as $user) {
        $this->actingAs($user)->get('/knowledge')->assertOk();
    }
});

// Dipisah karena actingAs bertahan sepanjang satu test — pemeriksaan tamu
// harus berjalan di test sendiri agar benar-benar tanpa sesi.
test('tamu diarahkan ke login saat membuka knowledge center', function () {
    $this->get('/knowledge')->assertRedirect('/login');
});

test('pengumuman draft tidak terlihat pembaca', function () {
    $draft = Announcement::whereNull('published_at')->firstOrFail();

    $response = $this->actingAs($this->staff)->get('/knowledge');

    $titles = collect($response->viewData('page')['props']['announcements'])
        ->pluck('title');

    expect($titles)->not->toContain($draft->title);
});

test('pengumuman bertarget divisi hanya terlihat divisi tersebut', function () {
    $staff = $this->staff->employee;
    $divisiLain = Department::where('id', '!=', $staff->department_id)->firstOrFail();

    Announcement::create([
        'title' => 'Rapat Divisi Lain',
        'body' => 'Hanya untuk divisi lain.',
        'category' => 'info',
        'target_type' => 'department',
        'target_department_id' => $divisiLain->id,
        'published_at' => now(),
    ]);

    Announcement::create([
        'title' => 'Rapat Divisi Saya',
        'body' => 'Untuk divisi karyawan ini.',
        'category' => 'info',
        'target_type' => 'department',
        'target_department_id' => $staff->department_id,
        'published_at' => now(),
    ]);

    $titles = collect(
        $this->actingAs($this->staff)->get('/knowledge')
            ->viewData('page')['props']['announcements'],
    )->pluck('title');

    expect($titles)->toContain('Rapat Divisi Saya')
        ->and($titles)->not->toContain('Rapat Divisi Lain');
});

test('pengumuman bertarget entitas hanya terlihat entitas tersebut', function () {
    Announcement::create([
        'title' => 'Khusus Mitra',
        'body' => 'Ketentuan invoice mitra.',
        'category' => 'info',
        'target_type' => 'employment_category',
        'target_category' => 'mitra',
        'published_at' => now(),
    ]);

    $judulMitra = collect(
        $this->actingAs($this->mitra)->get('/knowledge')
            ->viewData('page')['props']['announcements'],
    )->pluck('title');

    $judulKaryawan = collect(
        $this->actingAs($this->staff)->get('/knowledge')
            ->viewData('page')['props']['announcements'],
    )->pluck('title');

    expect($judulMitra)->toContain('Khusus Mitra')
        ->and($judulKaryawan)->not->toContain('Khusus Mitra');
});

test('dokumen tersimpan di disk privat, bukan disk publik', function () {
    Storage::fake('local');

    $this->actingAs($this->admin)->post('/knowledge/dokumen', [
        'title' => 'SOP Uji',
        'doc_type' => 'sop',
        'target_type' => 'all',
        'file' => UploadedFile::fake()->create('sop.pdf', 40, 'application/pdf'),
    ])->assertRedirect();

    $doc = KnowledgeDocument::where('title', 'SOP Uji')->firstOrFail();

    Storage::disk('local')->assertExists($doc->file_path);
    expect(Storage::disk('public')->exists($doc->file_path))->toBeFalse();
});

test('karyawan tidak dapat mengunduh dokumen yang bukan haknya', function () {
    $divisiLain = Department::where('id', '!=', $this->staff->employee->department_id)
        ->firstOrFail();

    $doc = KnowledgeDocument::create([
        'title' => 'SOP Divisi Lain',
        'doc_type' => 'sop',
        'file_path' => 'knowledge-documents/rahasia.pdf',
        'original_name' => 'rahasia.pdf',
        'file_size' => 10,
        'target_type' => 'department',
        'target_department_id' => $divisiLain->id,
    ]);

    $this->actingAs($this->staff)
        ->get("/knowledge/dokumen/{$doc->id}")
        ->assertForbidden();
});

test('unduhan dokumen menambah penghitung', function () {
    $doc = KnowledgeDocument::where('target_type', 'all')->firstOrFail();
    $awal = $doc->download_count;

    $this->actingAs($this->staff)
        ->get("/knowledge/dokumen/{$doc->id}")
        ->assertOk();

    expect($doc->fresh()->download_count)->toBe($awal + 1);
});

test('hanya super admin yang dapat mengelola konten', function () {
    foreach ([$this->staff, $this->mitra] as $user) {
        $this->actingAs($user)->get('/knowledge/kelola')->assertForbidden();
        $this->actingAs($user)->post('/knowledge/pengumuman', [
            'title' => 'Percobaan',
            'body' => 'Isi',
            'category' => 'info',
            'target_type' => 'all',
            'is_pinned' => false,
            'publish' => true,
        ])->assertForbidden();
    }

    $this->assertDatabaseMissing('announcements', ['title' => 'Percobaan']);
});

test('mengganti target membersihkan kolom target lama', function () {
    $announcement = Announcement::create([
        'title' => 'Awalnya per divisi',
        'body' => 'Isi',
        'category' => 'info',
        'target_type' => 'department',
        'target_department_id' => Department::first()->id,
        'published_at' => now(),
    ]);

    $this->actingAs($this->admin)->patch("/knowledge/pengumuman/{$announcement->id}", [
        'title' => 'Kini untuk semua',
        'body' => 'Isi',
        'category' => 'info',
        'target_type' => 'all',
        'is_pinned' => false,
        'publish' => true,
    ])->assertRedirect();

    $announcement->refresh();

    expect($announcement->target_type)->toBe('all')
        ->and($announcement->target_department_id)->toBeNull();
});

test('ketiga entitas kerja berhak cuti tahunan', function () {
    $policy = app(App\Services\LeavePolicyService::class);

    foreach (['probation', 'pkwt', 'mitra'] as $category) {
        $employee = Employee::whereHas(
            'employmentType',
            fn ($query) => $query->where('category', $category),
        )->firstOrFail();

        $balance = $policy->balance($employee);

        expect($employee->isLeaveEligible())
            ->toBeTrue("entitas {$category} seharusnya berhak cuti")
            ->and($balance['quota'])->toBeGreaterThan(0)
            // Aturan BPJS tidak ikut berubah.
            ->and($policy->validateRequest($employee, 'annual'))->toBeNull();
    }
});

// Kebijakan perusahaan berubah: ketiga entitas kini didaftarkan BPJS dan
// iurannya ditanggung perusahaan. Rincian perhitungannya diuji pada
// SalesCompensationTest.
test('ketiga entitas kerja didaftarkan BPJS', function () {
    foreach (['probation', 'pkwt', 'mitra'] as $category) {
        $employee = Employee::whereHas(
            'employmentType',
            fn ($query) => $query->where('category', $category),
        )->firstOrFail();

        expect($employee->isBpjsEligible())->toBeTrue("entitas {$category}");
    }
});
