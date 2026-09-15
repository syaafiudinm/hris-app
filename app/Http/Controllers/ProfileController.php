<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\AccountProvisioningService;
use App\Services\EmployeeDocumentService;
use App\Support\EmployeeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data diri & dokumen kelengkapan — diisi sendiri oleh pemilik akun.
 *
 * Departemen, posisi, tanggal bergabung, dan status kerja sama hanya
 * ditampilkan: nilainya menentukan hak cuti, BPJS, dan payroll sehingga
 * tetap dikelola HR lewat data induk.
 */
class ProfileController extends Controller
{
    public const COOPERATION_LABELS = [
        'probation' => 'Probation',
        'pkwt' => 'PKWT',
        'mitra' => 'Mitra',
    ];

    public function __construct(private EmployeeDocumentService $documents) {}

    public function edit(Request $request): Response
    {
        $employee = $this->currentEmployee($request);
        $employee->load(['department', 'employmentType']);

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'full_name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                ...EmployeeProfile::formValues($employee),
            ],
            'employment' => [
                'nik' => $employee->nik,
                'department' => $employee->department?->name,
                'position' => $employee->position,
                'joinDate' => $employee->join_date?->translatedFormat('d F Y'),
                'cooperation' => self::COOPERATION_LABELS[$employee->employmentType?->category] ?? null,
                'employmentType' => $employee->employmentType?->name,
            ],
            'summary' => [
                'completion' => $employee->profileCompletion(),
                'missingFields' => $employee->missingProfileFields(),
                'missingDocuments' => $employee->missingRequiredDocuments(),
            ],
            'documents' => $this->documents->present($employee),
            'options' => EmployeeProfile::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            // Email aktif sekaligus email login, jadi harus unik.
            'email' => ['required', 'email', 'max:150', Rule::unique('employees', 'email')->ignore($employee)],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s]{8,30}$/'],
            ...EmployeeProfile::rules($employee, required: true),
        ], [
            'phone.regex' => 'No WhatsApp hanya boleh berisi angka.',
            ...EmployeeProfile::messages(),
        ]);

        DB::transaction(function () use ($employee, $data) {
            $employee->update($data);
            app(AccountProvisioningService::class)->syncProfile($employee);
        });

        return back()->with('success', 'Data diri berhasil disimpan.');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);
        $document = $this->documents->storeFromRequest($request, $employee);

        $label = EmployeeDocument::TYPES[$document->type]['label'];

        return back()->with('success', "Dokumen {$label} berhasil diunggah.");
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee()->first();

        abort_if(! $employee, 403, 'Akun Anda belum tertaut ke data tenaga kerja.');

        return $employee;
    }
}
