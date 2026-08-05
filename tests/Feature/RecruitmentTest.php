<?php

use App\Models\Applicant;
use App\Models\EmploymentType;
use App\Models\JobVacancy;
use App\Models\User;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
});

test('public users can view vacancies and submit application', function () {
    $vacancy = JobVacancy::first();

    $response = $this->get("/karier/{$vacancy->id}");
    $response->assertStatus(200);

    $response = $this->post("/karier/{$vacancy->id}/apply", [
        'full_name' => 'Budi Applicant',
        'email' => 'budi.applicant@example.com',
        'phone' => '08123456789',
        'cv' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('applicants', [
        'email' => 'budi.applicant@example.com',
        'stage' => 'applied',
    ]);
});

test('super admin can view recruitment pipeline and change stage', function () {
    $admin = User::where('role', 'super_admin')->first();
    $applicant = Applicant::first();

    $response = $this->actingAs($admin)->get('/rekrutmen');
    $response->assertStatus(200);

    $response = $this->actingAs($admin)->patch("/rekrutmen/{$applicant->id}/stage", [
        'stage' => 'screening',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('applicants', [
        'id' => $applicant->id,
        'stage' => 'screening',
    ]);
});

test('super admin can convert applicant to hired employee', function () {
    $admin = User::where('role', 'super_admin')->first();
    // Pelamar berstatus rejected sengaja ditolak konversinya, jadi pilih
    // yang masih berada dalam pipeline aktif.
    $applicant = Applicant::whereNull('converted_employee_id')
        ->where('stage', '!=', 'rejected')
        ->first();
    $employmentType = EmploymentType::where('code', 'PKWT6')->first();

    $response = $this->actingAs($admin)->post("/rekrutmen/{$applicant->id}/convert", [
        'employment_type_id' => $employmentType->id,
        'basic_salary' => 6000000,
        'position' => 'Software Engineer',
    ]);

    $response->assertRedirect();
    $applicant->refresh();
    $this->assertNotNull($applicant->converted_employee_id);
    $this->assertEquals('hired', $applicant->stage);
});
