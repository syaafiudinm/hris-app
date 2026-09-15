<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Akses berkas dokumen karyawan. Pemilik dan HR saja — dicek per dokumen.
 */
class EmployeeDocumentController extends Controller
{
    public function __construct(private EmployeeDocumentService $documents) {}

    public function show(Request $request, EmployeeDocument $document): StreamedResponse
    {
        abort_unless($this->documents->canAccess($request->user(), $document), 403, 'Anda tidak berhak membuka dokumen ini.');

        $disk = Storage::disk(EmployeeDocumentService::DISK);

        abort_if(! $disk->exists($document->file_path), 404, 'Berkas dokumen tidak ditemukan.');

        // Dibuka inline supaya PDF & foto bisa langsung dilihat di browser.
        return $disk->response($document->file_path, $document->original_name);
    }

    /**
     * HR mengunggahkan dokumen atas nama karyawan.
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $document = $this->documents->storeFromRequest($request, $employee);
        $label = EmployeeDocument::TYPES[$document->type]['label'];

        return back()->with('success', "Dokumen {$label} milik {$employee->full_name} berhasil diunggah.");
    }

    public function destroy(Request $request, EmployeeDocument $document): RedirectResponse
    {
        abort_unless($this->documents->canAccess($request->user(), $document), 403, 'Anda tidak berhak menghapus dokumen ini.');

        $label = EmployeeDocument::TYPES[$document->type]['label'] ?? $document->type;
        $this->documents->delete($document);

        return back()->with('success', "Dokumen {$label} dihapus.");
    }
}
