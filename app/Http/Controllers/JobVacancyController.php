<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\JobVacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen Lowongan — sumber data Portal Karier Publik dan pipeline ATS.
 */
class JobVacancyController extends Controller
{
    public const STATUSES = ['draft', 'open', 'closed'];

    public const CATEGORIES = ['probation', 'pkwt', 'mitra'];

    public function index(Request $request): Response
    {
        $vacancies = JobVacancy::with('department')
            ->withCount([
                'applicants',
                'applicants as hired_count' => fn (Builder $query) => $query->where('stage', 'hired'),
            ])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->string('category')->toString(), fn (Builder $query, string $category) => $query->where('offered_category', $category))
            ->when($request->integer('department_id'), fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (JobVacancy $vacancy) => [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'category' => $vacancy->offered_category,
                'department_id' => $vacancy->department_id,
                'department' => $vacancy->department?->name,
                'location' => $vacancy->location,
                'description' => $vacancy->description,
                'requirements' => $vacancy->requirements,
                'quota' => $vacancy->quota,
                'status' => $vacancy->status,
                'publishedAt' => $vacancy->published_at?->translatedFormat('d M Y'),
                'publishedAtRaw' => $vacancy->published_at?->toDateString(),
                'applicantCount' => $vacancy->applicants_count,
                'hiredCount' => $vacancy->hired_count,
            ]);

        return Inertia::render('Vacancies/Index', [
            'vacancies' => $vacancies,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'category' => $request->string('category')->toString() ?: null,
                'department_id' => $request->integer('department_id') ?: null,
            ],
            'options' => [
                'departments' => Department::orderBy('name')->get(['id', 'name'])->all(),
                'statuses' => self::STATUSES,
                'categories' => self::CATEGORIES,
            ],
            'stats' => [
                'total' => JobVacancy::count(),
                'open' => JobVacancy::where('status', 'open')->count(),
                'draft' => JobVacancy::where('status', 'draft')->count(),
                'totalApplicants' => JobVacancy::withCount('applicants')->get()->sum('applicants_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vacancy = JobVacancy::create($this->validated($request));

        return back()->with('success', "Lowongan \"{$vacancy->title}\" dibuat.");
    }

    public function update(Request $request, JobVacancy $vacancy): RedirectResponse
    {
        $vacancy->update($this->validated($request));

        return back()->with('success', "Lowongan \"{$vacancy->title}\" diperbarui.");
    }

    /**
     * Buka / tutup lowongan tanpa perlu membuka form penuh.
     */
    public function toggleStatus(Request $request, JobVacancy $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $vacancy->update([
            'status' => $data['status'],
            // Tanggal publikasi diisi saat pertama kali dibuka agar
            // urutan di portal karier tetap masuk akal.
            'published_at' => $data['status'] === 'open' && ! $vacancy->published_at
                ? now()
                : $vacancy->published_at,
        ]);

        return back()->with('success', "Status lowongan diubah menjadi {$data['status']}.");
    }

    public function destroy(JobVacancy $vacancy): RedirectResponse
    {
        // Lowongan yang sudah punya pelamar tidak dihapus supaya riwayat
        // rekrutmen tidak ikut hilang — cukup ditutup.
        if ($vacancy->applicants()->exists()) {
            return back()->with(
                'error',
                'Lowongan ini sudah memiliki pelamar. Tutup lowongan alih-alih menghapusnya.',
            );
        }

        $title = $vacancy->title;
        $vacancy->delete();

        return back()->with('success', "Lowongan \"{$title}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'offered_category' => ['required', Rule::in(self::CATEGORIES)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'quota' => ['required', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
