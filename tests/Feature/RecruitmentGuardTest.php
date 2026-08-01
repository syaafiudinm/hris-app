<?php

/**
 * Regresi untuk perbaikan modul ATS — setiap test di sini mewakili satu bug
 * yang pernah lolos ke branch dan tidak boleh kembali.
 */

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobVacancy;
use App\Models\User;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->first();
});

function freshApplicant(): Applicant
{
    return Applicant::whereNull('converted_employee_id')
        ->where('stage', '!=', 'rejected')
        ->firstOrFail();
}

test('konversi ke mitra tanpa skema ditolak, bukan error 500', function () {
    $applicant = freshApplicant();
    $mitra = EmploymentType::where('category', 'mitra')->firstOrFail();

    $response = $this->actingAs($this->admin)->post("/rekrutmen/{$applicant->id}/convert", [
        'employment_type_id' => $mitra->id,
        'basic_salary' => 0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    expect($applicant->fresh()->converted_employee_id)->toBeNull();
});

test('konversi ke mitra dengan skema menghasilkan skema pembayaran', function () {
    $applicant = freshApplicant();
    $mitra = EmploymentType::where('category', 'mitra')->firstOrFail();

    $this->actingAs($this->admin)->post("/rekrutmen/{$applicant->id}/convert", [
        'employment_type_id' => $mitra->id,
        'basic_salary' => 0,
        'contract_end' => now()->addMonths(6)->toDateString(),
        'schema_type' => 'hourly',
        'rate_per_unit' => 150_000,
        'unit_label' => 'jam',
        'tax_scheme' => 'pph23',
        'custom_tax_percentage' => 2,
    ]);

    $employee = $applicant->fresh()->convertedEmployee;

    expect($employee)->not->toBeNull()
        ->and($employee->mitraPayrollSchema)->not->toBeNull()
        ->and($employee->mitraPayrollSchema->schema_type)->toBe('hourly')
        // Mitra tanpa duration_months tetap punya tanggal akhir agar
        // masuk peringatan kontrak H-30.
        ->and($employee->contract_end)->not->toBeNull();
});

test('pelamar berstatus rejected tidak dapat dikonversi', function () {
    $applicant = freshApplicant();
    $applicant->update(['stage' => 'rejected']);

    $response = $this->actingAs($this->admin)->post("/rekrutmen/{$applicant->id}/convert", [
        'employment_type_id' => EmploymentType::where('code', 'PKWT6')->value('id'),
        'basic_salary' => 6_000_000,
    ]);

    $response->assertSessionHas('error');
    expect($applicant->fresh()->converted_employee_id)->toBeNull();
});

test('NIK hasil konversi tidak bentrok dengan NIK yang diinput manual', function () {
    $maxId = Employee::max('id');
    $nikBentrok = sprintf('EMP-%04d', $maxId + 1);

    Employee::create([
        'employment_type_id' => EmploymentType::where('code', 'PKWT12')->value('id'),
        'nik' => $nikBentrok,
        'full_name' => 'Karyawan Input Manual',
        'join_date' => now(),
        'basic_salary' => 5_000_000,
        'status' => 'active',
    ]);

    $applicant = freshApplicant();

    $this->actingAs($this->admin)->post("/rekrutmen/{$applicant->id}/convert", [
        'employment_type_id' => EmploymentType::where('code', 'PKWT6')->value('id'),
        'basic_salary' => 6_000_000,
    ]);

    $employee = $applicant->fresh()->convertedEmployee;

    expect($employee)->not->toBeNull()
        ->and($employee->nik)->not->toBe($nikBentrok);
});

test('CV disimpan di disk privat dan hanya dapat diunduh super admin', function () {
    Storage::fake('local');

    $vacancy = JobVacancy::where('status', 'open')->firstOrFail();

    $this->post("/karier/{$vacancy->id}/apply", [
        'full_name' => 'Pelamar Uji',
        'email' => 'pelamar.uji@example.com',
        'phone' => '08123',
        'cv' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
    ]);

    $applicant = Applicant::where('email', 'pelamar.uji@example.com')->firstOrFail();

    // Berkas ada di disk privat, bukan di disk publik yang terbuka.
    Storage::disk('local')->assertExists($applicant->cv_path);
    expect(Storage::disk('public')->exists($applicant->cv_path))->toBeFalse();

    // Route unduh dijaga RBAC.
    $this->actingAs($this->admin)->get("/rekrutmen/{$applicant->id}/cv")->assertOk();
    $this->actingAs(User::where('role', 'manager')->first())
        ->get("/rekrutmen/{$applicant->id}/cv")
        ->assertForbidden();
});

test('tamu tidak dapat mengunduh CV pelamar', function () {
    Storage::fake('local');

    $vacancy = JobVacancy::where('status', 'open')->firstOrFail();

    $this->post("/karier/{$vacancy->id}/apply", [
        'full_name' => 'Pelamar Rahasia',
        'email' => 'rahasia@example.com',
        'cv' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
    ]);

    $applicant = Applicant::where('email', 'rahasia@example.com')->firstOrFail();

    $this->get("/rekrutmen/{$applicant->id}/cv")->assertRedirect('/login');
});

test('halaman detail kandidat menyediakan data form konversi', function () {
    $applicant = freshApplicant();

    $response = $this->actingAs($this->admin)->get("/rekrutmen/{$applicant->id}");

    $response->assertOk();

    // Tanpa options, form konversi di halaman detail tidak dapat dirender —
    // itulah sebabnya dulu tombol Hired di sini hanya mengubah tahap.
    $response->assertInertia(
        fn ($page) => $page
            ->has('options.employmentTypes')
            ->has('options.departments')
            ->has('options.schemaTypes')
            ->has('options.taxSchemes'),
    );
});

test('papan pipeline dan halaman detail memakai opsi konversi yang sama', function () {
    $applicant = freshApplicant();

    $pipeline = $this->actingAs($this->admin)->get('/rekrutmen');
    $detail = $this->actingAs($this->admin)->get("/rekrutmen/{$applicant->id}");

    $optionsPipeline = $pipeline->viewData('page')['props']['options'];
    $optionsDetail = $detail->viewData('page')['props']['options'];

    expect($optionsDetail)->toEqual($optionsPipeline);
});

test('ekspor pelamar menghormati filter lowongan', function () {
    $vacancy = JobVacancy::has('applicants')
        ->withCount('applicants')
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->get("/export/pelamar?format=csv&vacancy_id={$vacancy->id}")
        ->assertOk();

    $log = App\Models\ExportLog::latest()->firstOrFail();

    expect($log->row_count)
        ->toBe($vacancy->applicants_count)
        ->and($log->row_count)->toBeLessThan(Applicant::count())
        ->and($log->filters['vacancy_id'])->toBe($vacancy->id);
});
