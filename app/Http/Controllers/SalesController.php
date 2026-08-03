<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalesProduct;
use App\Models\SalesRecord;
use App\Services\ExportService;
use App\Services\PayrollCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Pencatatan penjualan mitra — sumber angka insentif dan bonus tier
 * pada skema kompensasi penjualan.
 */
class SalesController extends Controller
{
    public function __construct(private PayrollCalculator $calculator) {}

    public function index(Request $request): Response
    {
        [$year, $month] = $this->period($request);

        $products = SalesProduct::orderBy('id')->get();
        $activeProducts = $products->where('is_active', true);

        $mitra = Employee::active()
            ->whereHas('mitraPayrollSchema', fn (Builder $query) => $query->where('schema_type', 'sales'))
            ->with(['department', 'mitraPayrollSchema'])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
            ->orderBy('full_name')
            ->get();

        $records = SalesRecord::forPeriod($year, $month)
            ->whereIn('employee_id', $mitra->pluck('id'))
            ->get()
            ->groupBy('employee_id');

        $rows = $mitra->map(function (Employee $employee) use ($records, $activeProducts) {
            $owned = $records->get($employee->id, collect())->keyBy('sales_product_id');
            $config = $employee->mitraPayrollSchema?->components ?? [];

            $quantities = [];
            $incentive = 0.0;
            $totalUnits = 0;

            foreach ($activeProducts as $product) {
                $quantity = (int) ($owned->get($product->id)?->quantity ?? 0);
                $quantities[$product->id] = $quantity;
                $incentive += $quantity * (float) $product->incentive_amount;
                $totalUnits += $quantity;
            }

            $bonusPercentage = $this->calculator->bonusPercentageForUnits(
                $totalUnits,
                $config['bonus_tiers'] ?? [],
            );

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'nik' => $employee->nik,
                'department' => $employee->department?->name,
                'quantities' => $quantities,
                'totalUnits' => $totalUnits,
                'incentive' => $incentive,
                'bonusPercentage' => $bonusPercentage,
                'bonusAmount' => (float) ($config['ump_reference'] ?? 0) * ($bonusPercentage / 100),
            ];
        })->values()->all();

        return Inertia::render('Sales/Index', [
            'rows' => $rows,
            'products' => $products->map(fn (SalesProduct $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'incentive' => (float) $product->incentive_amount,
                'isActive' => $product->is_active,
                'soldThisPeriod' => (int) SalesRecord::forPeriod($year, $month)
                    ->where('sales_product_id', $product->id)
                    ->sum('quantity'),
            ])->all(),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'search' => $request->string('search')->toString() ?: null,
            ],
            'options' => [
                'years' => range(CarbonImmutable::now()->year - 3, CarbonImmutable::now()->year),
            ],
            'summary' => [
                'periodLabel' => CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y'),
                'totalUnits' => array_sum(array_column($rows, 'totalUnits')),
                'totalIncentive' => array_sum(array_column($rows, 'incentive')),
                'mitraCount' => count($rows),
            ],
        ]);
    }

    /**
     * Simpan seluruh kuantitas satu mitra untuk periode terpilih.
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        DB::transaction(function () use ($data, $employee) {
            foreach ($data['quantities'] as $productId => $quantity) {
                $quantity = (int) $quantity;

                $keys = [
                    'employee_id' => $employee->id,
                    'sales_product_id' => (int) $productId,
                    'period_year' => $data['period_year'],
                    'period_month' => $data['period_month'],
                ];

                // Kuantitas nol tidak perlu menyimpan baris kosong.
                if ($quantity < 1) {
                    SalesRecord::where($keys)->delete();

                    continue;
                }

                SalesRecord::updateOrCreate($keys, ['quantity' => $quantity]);
            }
        });

        return back()->with(
            'success',
            "Penjualan {$employee->full_name} disimpan. Jalankan ulang payroll periode ini agar nilainya ikut terhitung.",
        );
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        SalesProduct::create($this->validatedProduct($request));

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function updateProduct(Request $request, SalesProduct $product): RedirectResponse
    {
        $product->update($this->validatedProduct($request, $product));

        return back()->with('success', "Produk {$product->name} diperbarui.");
    }

    public function destroyProduct(SalesProduct $product): RedirectResponse
    {
        if ($product->salesRecords()->exists()) {
            return back()->with(
                'error',
                'Produk ini sudah dipakai pada catatan penjualan. Nonaktifkan saja agar riwayatnya tetap utuh.',
            );
        }

        $product->delete();

        return back()->with('success', 'Produk dihapus.');
    }

    /**
     * Rekap penjualan & insentif per mitra.
     */
    public function export(Request $request, ExportService $exporter): HttpResponse
    {
        [$year, $month] = $this->period($request);

        $rows = SalesRecord::with(['employee.department', 'product'])
            ->forPeriod($year, $month)
            ->get()
            ->map(fn (SalesRecord $record) => [
                $record->employee?->nik,
                $record->employee?->full_name,
                $record->employee?->department?->name,
                $record->product?->name,
                $record->quantity,
                (float) ($record->product?->incentive_amount ?? 0),
                $record->quantity * (float) ($record->product?->incentive_amount ?? 0),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Rekap Penjualan Mitra',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Rekap Penjualan & Insentif '.CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y'),
            headings: ['NIK', 'Nama Mitra', 'Divisi', 'Produk', 'Unit Terjual', 'Insentif/Unit', 'Total Insentif'],
            rows: $rows,
            filters: ['periode' => sprintf('%02d/%d', $month, $year)],
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = CarbonImmutable::now();

        return [
            (int) ($request->integer('year') ?: $now->year),
            (int) ($request->integer('month') ?: $now->month),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProduct(Request $request, ?SalesProduct $product = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('sales_products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:100'],
            'incentive_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
