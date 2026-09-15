<?php

/**
 * Data diri & dokumen kelengkapan karyawan.
 *
 * Dokumen berisi data pribadi (KTP, KK, rekening), jadi yang paling penting
 * diuji adalah batas aksesnya: pemilik dan HR saja.
 */

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\EmployeeDocumentService;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(EmployeeDocumentService::DISK);

    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->firstOrFail();

    $pegawai = Employee::active()
        ->whereNotNull('user_id')
        ->whereHas('user', fn ($query) => $query->where('role', 'employee'))
        ->limit(2)
        ->get();

    $this->employee = $pegawai[0];
    $this->other = $pegawai[1];
});

/**
 * @return array<string, mixed>
 */
function isianDataDiri(array $ubah = []): array
{
    return array_merge([
        'full_name' => 'Andi Pratama',
        'email' => 'andi.pratama@contoh.id',
        'phone' => '081234567890',
        'ktp_number' => '7371010101900001',
        'birth_place' => 'Makassar',
        'birth_date' => '1995-04-12',
        'gender' => 'male',
        'religion' => 'islam',
        'marital_status' => 'kawin',
        'dependents_count' => 2,
        'ktp_address' => 'Jl. Perintis Kemerdekaan No. 10, Makassar',
        'domicile_address' => 'Jl. Pettarani No. 5, Makassar',
        'emergency_contact_name' => 'Siti Aminah',
        'emergency_contact_relation' => 'Istri',
        'emergency_contact_phone' => '081298765432',
    ], $ubah);
}

function pdfPalsu(string $name = 'dokumen.pdf', int $kilobytes = 120): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

test('keenam departemen perusahaan tersedia', function () {
    expect(Department::pluck('name')->all())
        ->toContain('Sales', 'Aftersales', 'HRGA', 'FAT', 'Marketing', 'Operasional');
});

test('karyawan dapat membuka halaman data diri', function () {
    $this->actingAs($this->employee->user)
        ->get('/data-diri')
        ->assertOk();
});

test('karyawan menyimpan data diri dan akun login ikut diselaraskan', function () {
    $this->actingAs($this->employee->user)
        ->put('/data-diri', isianDataDiri())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $employee = $this->employee->fresh();

    expect($employee->ktp_number)->toBe('7371010101900001')
        ->and($employee->birth_date->toDateString())->toBe('1995-04-12')
        ->and($employee->dependents_count)->toBe(2)
        ->and($employee->missingProfileFields())->toBe([])
        ->and($employee->user->name)->toBe('Andi Pratama')
        ->and($employee->user->email)->toBe('andi.pratama@contoh.id');
});

test('data diri wajib lengkap dan no KTP harus 16 digit', function () {
    $this->actingAs($this->employee->user)
        ->put('/data-diri', isianDataDiri([
            'ktp_number' => '12345',
            'religion' => '',
            'emergency_contact_phone' => '',
        ]))
        ->assertSessionHasErrors(['ktp_number', 'religion', 'emergency_contact_phone']);
});

test('no KTP tidak boleh sama dengan karyawan lain', function () {
    $this->other->update(['ktp_number' => '7371010101900001']);

    $this->actingAs($this->employee->user)
        ->put('/data-diri', isianDataDiri())
        ->assertSessionHasErrors('ktp_number');
});

test('karyawan tidak dapat mengubah departemen, posisi, maupun entitas kerjanya', function () {
    $before = $this->employee->only(['department_id', 'position', 'employment_type_id', 'join_date']);

    $this->actingAs($this->employee->user)
        ->put('/data-diri', isianDataDiri([
            'department_id' => Department::where('id', '!=', $before['department_id'])->value('id'),
            'position' => 'Direktur',
            'employment_type_id' => 999,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->employee->fresh()->only(array_keys($before)))->toEqual($before);
});

test('karyawan mengunggah dokumen PDF dan unggahan ulang menggantikan berkas lama', function () {
    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'ktp', 'file' => pdfPalsu('ktp-lama.pdf')])
        ->assertSessionHasNoErrors();

    $lama = EmployeeDocument::where('employee_id', $this->employee->id)->where('type', 'ktp')->firstOrFail();
    Storage::disk(EmployeeDocumentService::DISK)->assertExists($lama->file_path);

    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'ktp', 'file' => pdfPalsu('ktp-baru.pdf')])
        ->assertSessionHasNoErrors();

    $baru = EmployeeDocument::where('employee_id', $this->employee->id)->where('type', 'ktp')->sole();

    expect($baru->original_name)->toBe('ktp-baru.pdf');
    Storage::disk(EmployeeDocumentService::DISK)->assertMissing($lama->file_path);
    Storage::disk(EmployeeDocumentService::DISK)->assertExists($baru->file_path);
});

test('dokumen selain PDF dan berkas melebihi batas ukuran ditolak', function () {
    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'kk', 'file' => UploadedFile::fake()->image('kk.jpg')])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'kk', 'file' => pdfPalsu('kk.pdf', EmployeeDocument::DEFAULT_MAX_KB + 1)])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'bukan-jenis', 'file' => pdfPalsu()])
        ->assertSessionHasErrors('type');

    expect(EmployeeDocument::count())->toBe(0);
});

test('foto diri boleh berupa gambar', function () {
    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'photo', 'file' => UploadedFile::fake()->image('foto.jpg')])
        ->assertSessionHasNoErrors();

    expect(EmployeeDocument::where('type', 'photo')->count())->toBe(1);
});

test('dokumen hanya dapat dibuka pemiliknya dan HR', function () {
    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'bank_account', 'file' => pdfPalsu('rekening.pdf')]);

    $document = EmployeeDocument::where('type', 'bank_account')->sole();

    $this->actingAs($this->employee->user)->get("/dokumen-karyawan/{$document->id}")->assertOk();
    $this->actingAs($this->admin)->get("/dokumen-karyawan/{$document->id}")->assertOk();
    $this->actingAs($this->other->user)->get("/dokumen-karyawan/{$document->id}")->assertForbidden();
    $this->actingAs($this->other->user)->delete("/dokumen-karyawan/{$document->id}")->assertForbidden();

    expect($document->fresh())->not->toBeNull();
});

test('pemilik dapat menghapus dokumennya beserta berkasnya', function () {
    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'npwp', 'file' => pdfPalsu('npwp.pdf')]);

    $document = EmployeeDocument::where('type', 'npwp')->sole();

    $this->actingAs($this->employee->user)
        ->delete("/dokumen-karyawan/{$document->id}")
        ->assertRedirect();

    expect($document->fresh())->toBeNull();
    Storage::disk(EmployeeDocumentService::DISK)->assertMissing($document->file_path);
});

test('HR dapat mengunggahkan dokumen atas nama karyawan, role lain tidak', function () {
    $this->actingAs($this->admin)
        ->post("/employees/{$this->employee->id}/dokumen", ['type' => 'cv', 'file' => pdfPalsu('cv.pdf')])
        ->assertSessionHasNoErrors();

    expect($this->employee->documents()->where('type', 'cv')->exists())->toBeTrue();

    $this->actingAs($this->other->user)
        ->post("/employees/{$this->employee->id}/dokumen", ['type' => 'kk', 'file' => pdfPalsu('kk.pdf')])
        ->assertForbidden();
});

test('kelengkapan profil menghitung isian dan dokumen wajib', function () {
    $employee = $this->employee;
    $employee->update(isianDataDiri());

    // Isian lengkap, dokumen wajib belum ada satu pun.
    expect($employee->fresh()->profileCompletion())->toBeLessThan(100);

    foreach (EmployeeDocument::requiredTypes() as $type) {
        $this->actingAs($employee->user)
            ->post('/data-diri/dokumen', ['type' => $type, 'file' => pdfPalsu("{$type}.pdf")])
            ->assertSessionHasNoErrors();
    }

    expect($employee->fresh()->profileCompletion())->toBe(100)
        ->and($employee->fresh()->missingRequiredDocuments())->toBe([]);
});

test('HR boleh menyimpan data induk tanpa data diri', function () {
    $employee = $this->employee;

    $this->actingAs($this->admin)
        ->put("/employees/{$employee->id}", [
            'nik' => $employee->nik,
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'position' => $employee->position,
            'ptkp_status' => $employee->ptkp_status,
            'employment_type_id' => $employee->employment_type_id,
            'department_id' => $employee->department_id,
            'join_date' => $employee->join_date->toDateString(),
            'contract_start' => $employee->contract_start?->toDateString(),
            'contract_end' => $employee->contract_end?->toDateString(),
            'basic_salary' => (float) $employee->basic_salary,
            'status' => $employee->status,
            'ktp_number' => '',
            'gender' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect("/employees/{$employee->id}");
});

test('halaman yang menampilkan data diri dan dokumen terbuka tanpa galat', function () {
    $this->actingAs($this->employee->user)
        ->post('/data-diri/dokumen', ['type' => 'cv', 'file' => pdfPalsu('cv.pdf')]);

    $this->actingAs($this->admin)->get("/employees/{$this->employee->id}")->assertOk();
    $this->actingAs($this->admin)->get("/employees/{$this->employee->id}/edit")->assertOk();
    $this->actingAs($this->admin)->get('/export/tenaga-kerja?format=xlsx')->assertOk();
    $this->actingAs($this->employee->user)->get('/data-diri')->assertOk();
    $this->actingAs($this->employee->user)->get('/inventaris-saya')->assertOk();
    $this->actingAs($this->admin)->get('/inventaris')->assertOk();
});
