<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\JobVacancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal Karier Publik — route tanpa auth middleware.
 */
class CareerController extends Controller
{
    /**
     * Listing lowongan berstatus open.
     */
    public function index(Request $request): Response
    {
        $category = $request->string('category')->toString() ?: null;

        $vacancies = JobVacancy::where('status', 'open')
            ->with('department')
            ->when($category, fn ($query, $cat) => $query->where('offered_category', $cat))
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (JobVacancy $vacancy) => [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'category' => $vacancy->offered_category,
                'categoryLabel' => self::CATEGORY_LABELS[$vacancy->offered_category] ?? $vacancy->offered_category,
                'location' => $vacancy->location,
                'department' => $vacancy->department?->name,
                'quota' => $vacancy->quota,
                'description' => $vacancy->description,
                'publishedAt' => $vacancy->published_at?->translatedFormat('d M Y'),
                'applicantCount' => $vacancy->applicants()->count(),
            ]);

        return Inertia::render('Career/Index', [
            'vacancies' => $vacancies,
            'filters' => ['category' => $category],
        ]);
    }

    /**
     * Detail lowongan + form lamaran.
     */
    public function show(JobVacancy $vacancy): Response
    {
        if ($vacancy->status !== 'open') {
            abort(404);
        }

        $vacancy->load('department');

        return Inertia::render('Career/Show', [
            'vacancy' => [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'category' => $vacancy->offered_category,
                'categoryLabel' => self::CATEGORY_LABELS[$vacancy->offered_category] ?? $vacancy->offered_category,
                'location' => $vacancy->location,
                'department' => $vacancy->department?->name,
                'quota' => $vacancy->quota,
                'description' => $vacancy->description,
                'requirements' => $vacancy->requirements,
                'publishedAt' => $vacancy->published_at?->translatedFormat('d F Y'),
            ],
        ]);
    }

    /**
     * Simpan lamaran + upload CV.
     */
    public function apply(Request $request, JobVacancy $vacancy): RedirectResponse
    {
        if ($vacancy->status !== 'open') {
            abort(404);
        }

        // Honeypot anti-spam: field "website" harus kosong.
        if ($request->filled('website')) {
            return back()->with('success', 'Lamaran Anda telah dikirim.');
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $cvPath = $request->file('cv')->store('applicant-cv', 'public');

        Applicant::create([
            'job_vacancy_id' => $vacancy->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'cv_path' => $cvPath,
            'stage' => 'applied',
        ]);

        return back()->with('success', 'Lamaran Anda berhasil dikirim! Kami akan menghubungi Anda.');
    }

    public const CATEGORY_LABELS = [
        'probation' => 'Probation Track',
        'pkwt' => 'Full-time PKWT',
        'mitra' => 'Mitra / Freelance',
    ];
}
