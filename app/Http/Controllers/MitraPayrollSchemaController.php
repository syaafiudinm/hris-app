<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MitraPayrollSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Custom Payroll Schema Builder untuk Mitra (Masterplan §2.3.B).
 */
class MitraPayrollSchemaController extends Controller
{
    public const SCHEMA_TYPES = ['fixed_project', 'hourly', 'daily', 'milestone', 'unit', 'sales'];

    public const TAX_SCHEMES = [
        'pph21_berkesinambungan',
        'pph21_tidak_berkesinambungan',
        'pph23',
        'bebas_pajak',
    ];

    public function index(Request $request): Response
    {
        $mitra = Employee::query()
            ->whereHas('employmentType', fn (Builder $query) => $query->where('category', 'mitra'))
            ->with(['mitraPayrollSchema', 'department'])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
            ->when($request->boolean('unconfigured'), fn (Builder $query) => $query->whereDoesntHave('mitraPayrollSchema'))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'nik' => $employee->nik,
                'name' => $employee->full_name,
                'department' => $employee->department?->name,
                'status' => $employee->status,
                'schema' => $employee->mitraPayrollSchema ? [
                    'id' => $employee->mitraPayrollSchema->id,
                    'schemaType' => $employee->mitraPayrollSchema->schema_type,
                    'rate' => (float) $employee->mitraPayrollSchema->rate_per_unit,
                    'unitLabel' => $employee->mitraPayrollSchema->unit_label,
                    'taxScheme' => $employee->mitraPayrollSchema->tax_scheme,
                    'taxPercentage' => (float) $employee->mitraPayrollSchema->custom_tax_percentage,
                    'components' => $employee->mitraPayrollSchema->components ?? [],
                ] : null,
            ]);

        return Inertia::render('MitraSchemas/Index', [
            'mitra' => $mitra,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'unconfigured' => $request->boolean('unconfigured'),
            ],
            'options' => [
                'schemaTypes' => self::SCHEMA_TYPES,
                'taxSchemes' => self::TAX_SCHEMES,
            ],
            'stats' => [
                'total' => Employee::whereHas('employmentType', fn (Builder $query) => $query->where('category', 'mitra'))->count(),
                'unconfigured' => Employee::whereHas('employmentType', fn (Builder $query) => $query->where('category', 'mitra'))
                    ->whereDoesntHave('mitraPayrollSchema')
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        if (! $employee->isMitra()) {
            return back()->with('error', 'Skema custom hanya berlaku untuk entitas Mitra.');
        }

        $data = $this->validated($request);

        MitraPayrollSchema::updateOrCreate(
            ['employee_id' => $employee->id],
            $data + ['employee_id' => $employee->id],
        );

        return back()->with('success', "Skema pembayaran {$employee->full_name} disimpan.");
    }

    public function destroy(MitraPayrollSchema $mitraPayrollSchema): RedirectResponse
    {
        $mitraPayrollSchema->delete();

        return back()->with('success', 'Skema pembayaran dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'schema_type' => ['required', Rule::in(self::SCHEMA_TYPES)],
            'rate_per_unit' => ['required', 'numeric', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:50'],
            'tax_scheme' => ['required', Rule::in(self::TAX_SCHEMES)],
            'custom_tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'milestones' => ['nullable', 'array'],
            'milestones.*.name' => ['required_with:milestones', 'string', 'max:100'],
            'milestones.*.percentage' => ['required_with:milestones', 'numeric', 'min:0', 'max:100'],
            // Konfigurasi skema penjualan
            'monthly_allowance' => ['nullable', 'numeric', 'min:0'],
            'working_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'ump_reference' => ['nullable', 'numeric', 'min:0'],
            'incentive_tax_base_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'incentive_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bpjs_wage_base' => ['nullable', 'numeric', 'min:0'],
            'bonus_tiers' => ['nullable', 'array'],
            'bonus_tiers.*.units' => ['required_with:bonus_tiers', 'integer', 'min:1'],
            'bonus_tiers.*.percentage' => ['required_with:bonus_tiers', 'numeric', 'min:0', 'max:1000'],
        ]);

        // Komponen fleksibel disimpan sebagai JSON agar tidak perlu migrasi
        // saat kebijakan bonus/milestone/penjualan berubah.
        $components = array_filter([
            'transport_allowance' => $data['transport_allowance'] ?? null,
            'milestones' => $data['milestones'] ?? null,
            'monthly_allowance' => $data['monthly_allowance'] ?? null,
            'working_days' => $data['working_days'] ?? null,
            'ump_reference' => $data['ump_reference'] ?? null,
            'incentive_tax_base_percentage' => $data['incentive_tax_base_percentage'] ?? null,
            'incentive_tax_rate' => $data['incentive_tax_rate'] ?? null,
            'bpjs_wage_base' => $data['bpjs_wage_base'] ?? null,
            'bonus_tiers' => $data['bonus_tiers'] ?? null,
        ], fn ($value) => $value !== null);

        return [
            'schema_type' => $data['schema_type'],
            'rate_per_unit' => $data['rate_per_unit'],
            'unit_label' => $data['unit_label'] ?? null,
            'tax_scheme' => $data['tax_scheme'],
            'custom_tax_percentage' => $data['custom_tax_percentage'],
            'components' => $components ?: null,
        ];
    }
}
