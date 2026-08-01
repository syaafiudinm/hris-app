<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Modul 5 — Knowledge Center.
 *
 * Halaman baca terbuka untuk semua role, tetapi isinya disaring berdasarkan
 * divisi dan entitas kerja masing-masing. Pengelolaan khusus Super Admin / HR.
 */
class KnowledgeController extends Controller
{
    /**
     * Bulletin pengumuman + repositori dokumen untuk pembaca.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $announcements = Announcement::published()
            ->visibleTo($employee)
            ->with(['author', 'targetDepartment'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(30)
            ->get()
            ->map(fn (Announcement $item) => $this->presentAnnouncement($item));

        $documents = KnowledgeDocument::visibleTo($employee)
            ->with(['uploader', 'targetDepartment'])
            ->when($request->string('doc_type')->toString(), fn (Builder $query, string $type) => $query->where('doc_type', $type))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (KnowledgeDocument $doc) => $this->presentDocument($doc));

        return Inertia::render('Knowledge/Index', [
            'announcements' => $announcements,
            'documents' => $documents,
            'filters' => [
                'doc_type' => $request->string('doc_type')->toString() ?: null,
                'search' => $request->string('search')->toString() ?: null,
            ],
            'options' => [
                'docTypes' => KnowledgeDocument::DOC_TYPES,
                'docTypeLabels' => KnowledgeDocument::DOC_TYPE_LABELS,
            ],
        ]);
    }

    /**
     * Halaman kelola untuk HR.
     */
    public function manage(Request $request): Response
    {
        $announcements = Announcement::with(['author', 'targetDepartment'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Announcement $item) => $this->presentAnnouncement($item, forManager: true));

        $documents = KnowledgeDocument::with(['uploader', 'targetDepartment'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (KnowledgeDocument $doc) => $this->presentDocument($doc));

        return Inertia::render('Knowledge/Manage', [
            'announcements' => $announcements,
            'documents' => $documents,
            'options' => [
                'departments' => Department::orderBy('name')->get(['id', 'name'])->all(),
                'categories' => Announcement::CATEGORIES,
                'docTypes' => KnowledgeDocument::DOC_TYPES,
                'docTypeLabels' => KnowledgeDocument::DOC_TYPE_LABELS,
                'targetTypes' => Announcement::TARGET_TYPES,
                'employmentCategories' => ['probation', 'pkwt', 'mitra'],
            ],
            'stats' => [
                'published' => Announcement::published()->count(),
                'draft' => Announcement::whereNull('published_at')->count(),
                'documents' => KnowledgeDocument::count(),
                'downloads' => (int) KnowledgeDocument::sum('download_count'),
            ],
        ]);
    }

    /* ---------------------------------------------------------- Pengumuman */

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $this->validatedAnnouncement($request);

        Announcement::create($data + [
            'created_by' => $request->user()?->employee?->id,
        ]);

        return back()->with('success', 'Pengumuman disimpan.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validatedAnnouncement($request));

        return back()->with('success', 'Pengumuman diperbarui.');
    }

    /**
     * Terbitkan atau tarik kembali pengumuman.
     */
    public function toggleAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'publish' => ['required', 'boolean'],
        ]);

        $announcement->update([
            'published_at' => $data['publish'] ? ($announcement->published_at ?? now()) : null,
        ]);

        return back()->with(
            'success',
            $data['publish'] ? 'Pengumuman diterbitkan.' : 'Pengumuman ditarik menjadi draft.',
        );
    }

    public function destroyAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    /* ------------------------------------------------------------ Dokumen */

    public function storeDocument(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'doc_type' => ['required', Rule::in(KnowledgeDocument::DOC_TYPES)],
            'version' => ['nullable', 'string', 'max:20'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'target_type' => ['required', Rule::in(KnowledgeDocument::TARGET_TYPES)],
            'target_department_id' => ['nullable', 'required_if:target_type,department', 'exists:departments,id'],
            'target_category' => ['nullable', 'required_if:target_type,employment_category', Rule::in(['probation', 'pkwt', 'mitra'])],
        ]);

        $file = $request->file('file');

        // Dokumen internal disimpan di disk privat; diunduh lewat route ber-RBAC.
        $path = $file->store('knowledge-documents', 'local');

        KnowledgeDocument::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'doc_type' => $data['doc_type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            // Field nullable tidak selalu ada di hasil validate() — beri
            // penjaga agar tidak memicu "undefined array key".
            'version' => ($data['version'] ?? null) ?: '1.0',
            'target_type' => $data['target_type'],
            'target_department_id' => $data['target_type'] === 'department'
                ? $data['target_department_id']
                : null,
            'target_category' => $data['target_type'] === 'employment_category'
                ? $data['target_category']
                : null,
            'uploaded_by' => $request->user()?->employee?->id,
        ]);

        return back()->with('success', 'Dokumen diunggah.');
    }

    /**
     * Unduh dokumen. Pembaca hanya boleh mengambil dokumen yang memang
     * ditujukan kepadanya — pengecekan dilakukan ulang di sini, bukan hanya
     * saat menyusun daftar.
     */
    public function downloadDocument(Request $request, KnowledgeDocument $document): HttpResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            $allowed = KnowledgeDocument::whereKey($document->id)
                ->visibleTo($this->currentEmployee($request))
                ->exists();

            abort_if(! $allowed, 403, 'Dokumen ini tidak ditujukan untuk Anda.');
        }

        abort_if(
            ! Storage::disk('local')->exists($document->file_path),
            404,
            'Berkas dokumen tidak ditemukan di penyimpanan.',
        );

        $document->increment('download_count');

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(KnowledgeDocument $document): RedirectResponse
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    /* ------------------------------------------------------------ Helpers */

    private function currentEmployee(Request $request): ?Employee
    {
        return $request->user()?->employee()->with('employmentType')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAnnouncement(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(Announcement::CATEGORIES)],
            'target_type' => ['required', Rule::in(Announcement::TARGET_TYPES)],
            'target_department_id' => ['nullable', 'required_if:target_type,department', 'exists:departments,id'],
            'target_category' => ['nullable', 'required_if:target_type,employment_category', Rule::in(['probation', 'pkwt', 'mitra'])],
            'is_pinned' => ['required', 'boolean'],
            'publish' => ['required', 'boolean'],
        ]);

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'category' => $data['category'],
            'target_type' => $data['target_type'],
            // Kolom target dibersihkan agar tidak menyimpan sisa pilihan lama.
            'target_department_id' => $data['target_type'] === 'department'
                ? $data['target_department_id']
                : null,
            'target_category' => $data['target_type'] === 'employment_category'
                ? $data['target_category']
                : null,
            'is_pinned' => $data['is_pinned'],
            'published_at' => $data['publish'] ? now() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAnnouncement(Announcement $item, bool $forManager = false): array
    {
        $payload = [
            'id' => $item->id,
            'title' => $item->title,
            'body' => $item->body,
            'category' => $item->category,
            'isPinned' => $item->is_pinned,
            'audience' => $item->audienceLabel(),
            'author' => $item->author?->full_name,
            'publishedAt' => $item->published_at?->translatedFormat('d M Y'),
        ];

        if ($forManager) {
            $payload += [
                'isPublished' => $item->isPublished(),
                'targetType' => $item->target_type,
                'targetDepartmentId' => $item->target_department_id,
                'targetCategory' => $item->target_category,
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDocument(KnowledgeDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'title' => $doc->title,
            'description' => $doc->description,
            'docType' => $doc->doc_type,
            'typeLabel' => $doc->typeLabel(),
            'version' => $doc->version,
            'fileName' => $doc->original_name,
            'fileSize' => $doc->humanFileSize(),
            'audience' => $doc->audienceLabel(),
            'uploader' => $doc->uploader?->full_name,
            'downloadCount' => $doc->download_count,
            'uploadedAt' => $doc->created_at?->translatedFormat('d M Y'),
        ];
    }
}
