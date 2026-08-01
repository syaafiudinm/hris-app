<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobVacancy;
use App\Services\ExportService;
use App\Services\HiredConversionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Modul 4 — Rekrutmen (ATS): Pipeline Kanban, conversion, dokumen, dan ekspor.
 */
class RecruitmentController extends Controller
{
    public const STAGES = ['applied', 'screening', 'interview', 'offering', 'hired', 'rejected'];

    public const STAGE_LABELS = [
        'applied' => 'Applied',
        'screening' => 'Screening',
        'interview' => 'Interview',
        'offering' => 'Offering',
        'hired' => 'Hired',
        'rejected' => 'Rejected',
    ];

    /**
     * Pipeline Kanban — pelamar dikelompokkan per stage.
     */
    public function index(Request $request): Response
    {
        $vacancyId = $request->integer('vacancy_id') ?: null;
        $departmentId = $request->integer('department_id') ?: null;

        $applicants = Applicant::with(['jobVacancy.department'])
            ->when($vacancyId, fn ($q, $id) => $q->where('job_vacancy_id', $id))
            ->when($departmentId, fn ($q, $id) => $q->whereHas('jobVacancy', fn ($inner) => $inner->where('department_id', $id)))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Applicant $a) => [
                'id' => $a->id,
                'name' => $a->full_name,
                'email' => $a->email,
                'phone' => $a->phone,
                'stage' => $a->stage,
                'vacancyTitle' => $a->jobVacancy?->title,
                'vacancyCategory' => $a->jobVacancy?->offered_category,
                'department' => $a->jobVacancy?->department?->name,
                'hasCv' => (bool) $a->cv_path,
                'convertedEmployeeId' => $a->converted_employee_id,
                'appliedAt' => $a->created_at?->translatedFormat('d M Y'),
                'stageChangedAt' => $a->stage_changed_at?->translatedFormat('d M Y H:i'),
            ]);

        // Kelompokkan per stage (hanya pipeline stages, bukan rejected).
        $pipelineStages = ['applied', 'screening', 'interview', 'offering', 'hired'];
        $pipeline = [];
        foreach ($pipelineStages as $stage) {
            $pipeline[] = [
                'key' => $stage,
                'label' => self::STAGE_LABELS[$stage],
                'applicants' => $applicants->where('stage', $stage)->values()->all(),
            ];
        }

        // Rejected tersendiri.
        $rejected = $applicants->where('stage', 'rejected')->values()->all();

        $vacancies = JobVacancy::with('department')
            // withCount menghindari dua query tambahan per lowongan.
            ->withCount([
                'applicants',
                'applicants as hired_count' => fn ($query) => $query->where('stage', 'hired'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (JobVacancy $v) => [
                'id' => $v->id,
                'title' => $v->title,
                'department' => $v->department?->name,
                'status' => $v->status,
                'category' => $v->offered_category,
                'applicantCount' => $v->applicants_count,
                'hiredCount' => $v->hired_count,
            ]);

        return Inertia::render('Recruitment/Index', [
            'pipeline' => $pipeline,
            'rejected' => $rejected,
            'vacancies' => $vacancies,
            'filters' => [
                'vacancy_id' => $vacancyId,
                'department_id' => $departmentId,
            ],
            'options' => $this->conversionOptions(),
            'stats' => [
                'totalApplicants' => Applicant::count(),
                'totalHired' => Applicant::where('stage', 'hired')->count(),
                'totalOpen' => JobVacancy::where('status', 'open')->count(),
                'conversionRate' => Applicant::count() > 0
                    ? round((Applicant::where('stage', 'hired')->count() / Applicant::count()) * 100, 1)
                    : 0,
            ],
        ]);
    }

    /**
     * Detail kandidat: profil, riwayat tahap, catatan, pratinjau CV.
     */
    public function show(Applicant $applicant): Response
    {
        $applicant->load(['jobVacancy.department', 'convertedEmployee.employmentType']);

        return Inertia::render('Recruitment/Show', [
            'applicant' => [
                'id' => $applicant->id,
                'name' => $applicant->full_name,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'stage' => $applicant->stage,
                'stageLabel' => self::STAGE_LABELS[$applicant->stage] ?? $applicant->stage,
                'hasCv' => (bool) $applicant->cv_path,
                'notes' => $applicant->notes ?? [],
                'stageHistory' => $applicant->stage_history ?? [],
                'appliedAt' => $applicant->created_at?->translatedFormat('d F Y H:i'),
                'stageChangedAt' => $applicant->stage_changed_at?->translatedFormat('d F Y H:i'),
                'vacancy' => [
                    'id' => $applicant->jobVacancy?->id,
                    'title' => $applicant->jobVacancy?->title,
                    'category' => $applicant->jobVacancy?->offered_category,
                    'department' => $applicant->jobVacancy?->department?->name,
                ],
                'convertedEmployee' => $applicant->convertedEmployee ? [
                    'id' => $applicant->convertedEmployee->id,
                    'nik' => $applicant->convertedEmployee->nik,
                    'name' => $applicant->convertedEmployee->full_name,
                    'type' => $applicant->convertedEmployee->employmentType?->name,
                ] : null,
            ],
            'stages' => self::STAGES,
            'stageLabels' => self::STAGE_LABELS,
            // Sama dengan papan pipeline, supaya form konversi di halaman ini
            // menghasilkan data karyawan yang identik.
            'options' => $this->conversionOptions(),
        ]);
    }

    /**
     * Pilihan yang dibutuhkan form One-Click Hired Conversion.
     *
     * @return array<string, mixed>
     */
    private function conversionOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name'])->all(),
            'employmentTypes' => EmploymentType::orderBy('id')
                ->get(['id', 'name', 'code', 'category', 'duration_months'])
                ->all(),
            'schemaTypes' => MitraPayrollSchemaController::SCHEMA_TYPES,
            'taxSchemes' => MitraPayrollSchemaController::TAX_SCHEMES,
        ];
    }

    /**
     * Pindah stage kandidat.
     */
    public function updateStage(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(self::STAGES)],
        ]);

        $from = $applicant->stage;
        $to = $data['stage'];

        if ($from === $to) {
            return back()->with('error', 'Status sudah berada di tahap ini.');
        }

        $applicant->recordStageChange($from, $to, $request->user()?->name);

        return back()->with('success', "Status {$applicant->full_name} diubah ke " . self::STAGE_LABELS[$to] . '.');
    }

    /**
     * Tambah catatan interview.
     */
    public function addNote(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $notes = $applicant->notes ?? [];
        $notes[] = [
            'content' => $data['content'],
            'author' => $request->user()?->name ?? 'System',
            'created_at' => now()->toIso8601String(),
        ];

        $applicant->update(['notes' => $notes]);

        return back()->with('success', 'Catatan ditambahkan.');
    }

    /**
     * One-Click Hired Conversion — konversi pelamar menjadi karyawan.
     */
    public function convertToHired(Request $request, Applicant $applicant, HiredConversionService $service): RedirectResponse
    {
        $data = $request->validate([
            'employment_type_id' => ['required', 'exists:employment_types,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:100'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            // Entitas mitra tidak punya duration_months, jadi tanggal akhir
            // kontraknya diisi manual agar tetap masuk peringatan H-30.
            'contract_end' => ['nullable', 'date', 'after:today'],
            // Mitra schema fields (optional).
            'schema_type' => ['nullable', Rule::in(MitraPayrollSchemaController::SCHEMA_TYPES)],
            'rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'tax_scheme' => ['nullable', Rule::in(MitraPayrollSchemaController::TAX_SCHEMES)],
            'custom_tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Pelamar yang sudah ditolak tidak boleh langsung dikonversi —
        // kembalikan dulu ke tahap seleksi agar jejaknya jelas.
        if ($applicant->stage === 'rejected') {
            return back()->with(
                'error',
                'Pelamar berstatus Rejected. Kembalikan ke tahap seleksi sebelum dikonversi.',
            );
        }

        $employmentType = EmploymentType::find($data['employment_type_id']);

        $mitraSchemaData = null;

        if ($employmentType?->category === 'mitra') {
            // Masterplan §2.4: memilih Mitra wajib disertai skema pembayaran custom.
            // Tanpa skema, mitra akan dilewati diam-diam oleh mesin payroll.
            if (empty($data['schema_type'])) {
                return back()->with(
                    'error',
                    'Konversi ke Mitra wajib menyertakan skema pembayaran custom.',
                );
            }

            $mitraSchemaData = [
                'schema_type' => $data['schema_type'],
                'rate_per_unit' => $data['rate_per_unit'] ?? 0,
                'unit_label' => $data['unit_label'] ?? null,
                'tax_scheme' => $data['tax_scheme'] ?? 'pph21_tidak_berkesinambungan',
                'custom_tax_percentage' => $data['custom_tax_percentage'] ?? 2.5,
                'components' => null,
            ];
        }

        $employee = $service->convert(
            $applicant,
            $data,
            $mitraSchemaData,
            $request->user()?->name,
        );

        return back()->with('success', "{$applicant->full_name} berhasil dikonversi sebagai {$employmentType->name} (NIK: {$employee->nik}).");
    }

    /**
     * Unduh CV pelamar. CV disimpan di disk privat sehingga hanya bisa
     * diakses lewat route ini — yang sudah dijaga RBAC di routes/web.php.
     */
    public function downloadCv(Applicant $applicant): HttpResponse
    {
        abort_if(! $applicant->cv_path, 404, 'Pelamar ini tidak melampirkan CV.');
        abort_if(
            ! Storage::disk('local')->exists($applicant->cv_path),
            404,
            'Berkas CV tidak ditemukan di penyimpanan.',
        );

        return Storage::disk('local')->download(
            $applicant->cv_path,
            'cv-'.Str::slug($applicant->full_name).'.'.pathinfo($applicant->cv_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * PDF Offering Letter.
     */
    public function offeringLetter(Applicant $applicant): HttpResponse
    {
        $applicant->load('jobVacancy.department');

        return Pdf::loadView('documents.offering-letter', [
            'applicant' => $applicant,
            'vacancy' => $applicant->jobVacancy,
            'generatedAt' => now()->translatedFormat('d F Y'),
        ])
            ->setPaper('a4')
            ->download("offering-letter-{$applicant->id}.pdf");
    }

    /**
     * PDF Kontrak berdasarkan kategori entitas.
     */
    public function contract(Employee $employee): HttpResponse
    {
        $employee->load(['employmentType', 'department', 'mitraPayrollSchema']);
        $category = $employee->employmentType?->category ?? 'pkwt';

        $view = match ($category) {
            'probation' => 'documents.contract-probation',
            'mitra' => 'documents.contract-mitra',
            default => 'documents.contract-pkwt',
        };

        return Pdf::loadView($view, [
            'employee' => $employee,
            'schema' => $employee->mitraPayrollSchema,
            'generatedAt' => now()->translatedFormat('d F Y'),
        ])
            ->setPaper('a4')
            ->download("kontrak-{$employee->nik}.pdf");
    }

    /**
     * Ekspor Database Pelamar.
     */
    public function exportApplicants(Request $request, ExportService $exporter): HttpResponse
    {
        $filters = [
            'vacancy_id' => $request->integer('vacancy_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'stage' => $request->string('stage')->toString() ?: null,
        ];

        // Berkas yang diunduh harus sama dengan yang tampil di layar,
        // jadi filter pipeline ikut diterapkan di sini.
        $rows = Applicant::with('jobVacancy')
            ->when($filters['vacancy_id'], fn ($q, $id) => $q->where('job_vacancy_id', $id))
            ->when($filters['department_id'], fn ($q, $id) => $q->whereHas(
                'jobVacancy',
                fn ($inner) => $inner->where('department_id', $id),
            ))
            ->when($filters['stage'], fn ($q, $stage) => $q->where('stage', $stage))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Applicant $a) => [
                $a->full_name,
                $a->email,
                $a->phone,
                $a->jobVacancy?->title,
                self::STAGE_LABELS[$a->stage] ?? $a->stage,
                $a->created_at?->format('d/m/Y'),
                $a->stage_changed_at?->format('d/m/Y'),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Database Pelamar',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Database Pelamar',
            headings: ['Nama', 'Email', 'Telepon', 'Lowongan', 'Tahap', 'Tanggal Lamar', 'Terakhir Diubah'],
            rows: $rows,
            filters: $filters,
        );
    }

    /**
     * Ekspor Performa Lowongan.
     */
    public function exportVacancyPerformance(Request $request, ExportService $exporter): HttpResponse
    {
        $rows = JobVacancy::with('department')
            ->withCount(['applicants', 'applicants as hired_count' => fn ($q) => $q->where('stage', 'hired')])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (JobVacancy $v) => [
                $v->title,
                $v->department?->name,
                $v->offered_category,
                $v->quota,
                $v->applicants_count,
                $v->hired_count,
                $v->applicants_count > 0 ? round(($v->hired_count / $v->applicants_count) * 100, 1) . '%' : '0%',
                $v->status,
                $v->published_at?->format('d/m/Y'),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Performa Lowongan',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Laporan Performa Lowongan',
            headings: ['Lowongan', 'Divisi', 'Kategori', 'Kuota', 'Total Pelamar', 'Hired', 'Conversion Rate', 'Status', 'Dipublikasi'],
            rows: $rows,
        );
    }

    /**
     * Ekspor Conversion Rate.
     */
    public function exportConversionRate(Request $request, ExportService $exporter): HttpResponse
    {
        $stages = ['applied', 'screening', 'interview', 'offering', 'hired', 'rejected'];
        $counts = Applicant::selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $total = $counts->sum();
        $rows = array_map(fn ($stage) => [
            self::STAGE_LABELS[$stage] ?? $stage,
            (int) ($counts[$stage] ?? 0),
            $total > 0 ? round(((int) ($counts[$stage] ?? 0) / $total) * 100, 1) . '%' : '0%',
        ], $stages);

        return $exporter->download(
            $request,
            module: 'Conversion Rate ATS',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Laporan Conversion Rate Rekrutmen',
            headings: ['Tahap', 'Jumlah', 'Persentase'],
            rows: $rows,
        );
    }
}
