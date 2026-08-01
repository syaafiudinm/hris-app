<?php

namespace App\Services;

use App\Exports\TableExport;
use App\Models\ExportLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporting Engine (Masterplan §3).
 *
 * Format: xlsx / csv / pdf. Setiap ekspor dicatat ke audit log lengkap
 * dengan user, IP, waktu, filter, dan nama berkas.
 */
class ExportService
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    /**
     * @param  list<string>  $headings
     * @param  list<list<scalar|null>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function download(
        Request $request,
        string $module,
        string $format,
        string $title,
        array $headings,
        array $rows,
        array $filters = [],
    ): Response|BinaryFileResponse {
        $format = in_array($format, self::FORMATS, true) ? $format : 'xlsx';
        $fileName = $this->fileName($module, $format);

        $this->log($request, $module, $format, $fileName, $rows, $filters);

        if ($format === 'pdf') {
            return Pdf::loadView('exports.table', [
                'title' => $title,
                'headings' => $headings,
                'rows' => $rows,
                'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
                'generatedAt' => now()->translatedFormat('d F Y H:i'),
            ])
                ->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')
                ->download($fileName);
        }

        return Excel::download(
            new TableExport($headings, $rows, $title),
            $fileName,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    private function fileName(string $module, string $format): string
    {
        return sprintf('%s-%s.%s', Str::slug($module), now()->format('Ymd-His'), $format);
    }

    /**
     * @param  list<list<scalar|null>>  $rows
     * @param  array<string, mixed>  $filters
     */
    private function log(
        Request $request,
        string $module,
        string $format,
        string $fileName,
        array $rows,
        array $filters,
    ): void {
        ExportLog::create([
            'user_id' => $request->user()?->id,
            'module' => $module,
            'format' => $format,
            'file_name' => $fileName,
            'filters' => $filters,
            'row_count' => count($rows),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }
}
