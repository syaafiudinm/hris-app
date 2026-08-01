<?php

namespace App\Http\Controllers;

use App\Models\EmploymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Definisi Entitas Kerja — sumber aturan hak cuti & BPJS yang dibaca
 * rule engine payroll dan portal cuti.
 */
class EmploymentTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('EmploymentTypes/Index', [
            'types' => EmploymentType::withCount(['employees as active_count' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('id')
                ->get()
                ->map(fn (EmploymentType $type) => [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'category' => $type->category,
                    'durationMonths' => $type->duration_months,
                    'isLeaveEligible' => $type->is_leave_eligible,
                    'isBpjsEligible' => $type->is_bpjs_eligible,
                    'annualLeaveQuota' => $type->annual_leave_quota,
                    'activeCount' => $type->active_count,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        EmploymentType::create($this->validated($request));

        return back()->with('success', 'Entitas kerja baru ditambahkan.');
    }

    public function update(Request $request, EmploymentType $employmentType): RedirectResponse
    {
        $employmentType->update($this->validated($request, $employmentType));

        return back()->with('success', "Aturan {$employmentType->name} diperbarui.");
    }

    public function destroy(EmploymentType $employmentType): RedirectResponse
    {
        if ($employmentType->employees()->exists()) {
            return back()->with('error', 'Entitas masih dipakai oleh data tenaga kerja.');
        }

        $employmentType->delete();

        return back()->with('success', 'Entitas kerja dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EmploymentType $type = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('employment_types', 'code')->ignore($type)],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(['probation', 'pkwt', 'mitra'])],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_leave_eligible' => ['required', 'boolean'],
            'is_bpjs_eligible' => ['required', 'boolean'],
            'annual_leave_quota' => ['required', 'integer', 'min:0', 'max:60'],
        ]);
    }
}
