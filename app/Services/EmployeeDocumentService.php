<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Dokumen kelengkapan karyawan (KTP, KK, rekening, dst).
 *
 * Seluruhnya data pribadi, jadi disimpan di disk privat dan hanya dibuka
 * lewat route yang memeriksa kepemilikan — tidak pernah lewat URL publik.
 */
class EmployeeDocumentService
{
    public const DISK = 'local';

    /**
     * Validasi dan simpan satu dokumen. Unggahan pada jenis yang sudah terisi
     * menggantikan berkas lama.
     */
    public function storeFromRequest(Request $request, Employee $employee): EmployeeDocument
    {
        $request->validate([
            'type' => ['required', Rule::in(array_keys(EmployeeDocument::TYPES))],
        ]);

        $definition = EmployeeDocument::TYPES[$request->string('type')->toString()];

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', $definition['mimes']),
                'max:'.$definition['max_kb'],
            ],
        ], [
            'file.mimes' => 'Format berkas harus '.strtoupper(implode('/', $definition['mimes'])).'.',
            'file.max' => 'Ukuran berkas maksimal '.self::humanSize($definition['max_kb']).'.',
        ]);

        $type = $request->string('type')->toString();
        $file = $request->file('file');
        $path = $file->store(sprintf('employee-documents/%d', $employee->id), self::DISK);

        $previous = $employee->documents()->where('type', $type)->first();

        $document = DB::transaction(fn () => EmployeeDocument::updateOrCreate(
            ['employee_id' => $employee->id, 'type' => $type],
            [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()?->id,
            ],
        ));

        // Berkas lama baru dibuang setelah baris baru tersimpan, supaya
        // kegagalan di tengah jalan tidak menyisakan baris tanpa berkas.
        if ($previous && $previous->file_path !== $path) {
            Storage::disk(self::DISK)->delete($previous->file_path);
        }

        return $document;
    }

    public function delete(EmployeeDocument $document): void
    {
        Storage::disk(self::DISK)->delete($document->file_path);
        $document->delete();
    }

    /**
     * Pemilik dokumen dan HR (super admin) yang boleh membuka atau menghapus.
     */
    public function canAccess(User $user, EmployeeDocument $document): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $document->employee?->user_id === $user->id;
    }

    /**
     * Ringkasan slot dokumen untuk ditampilkan di halaman.
     *
     * @return list<array<string, mixed>>
     */
    public function present(Employee $employee): array
    {
        $uploaded = $employee->documents()->get()->keyBy('type');

        return collect(EmployeeDocument::TYPES)
            ->map(function (array $definition, string $type) use ($uploaded) {
                /** @var EmployeeDocument|null $document */
                $document = $uploaded->get($type);

                return [
                    'type' => $type,
                    'label' => $definition['label'],
                    'required' => $definition['required'],
                    'hint' => $definition['hint'],
                    'accept' => implode(',', array_map(fn (string $mime) => ".{$mime}", $definition['mimes'])),
                    'formats' => strtoupper(implode('/', array_unique(array_map(
                        fn (string $mime) => $mime === 'jpeg' ? 'jpg' : $mime,
                        $definition['mimes'],
                    )))),
                    'maxSize' => self::humanSize($definition['max_kb']),
                    'maxKb' => $definition['max_kb'],
                    'document' => $document ? [
                        'id' => $document->id,
                        'name' => $document->original_name,
                        'size' => self::humanSize((int) ceil($document->file_size / 1024)),
                        'uploadedAt' => $document->updated_at?->translatedFormat('d M Y H:i'),
                        'url' => route('employee-documents.show', $document),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    private static function humanSize(int $kilobytes): string
    {
        return $kilobytes >= 1024
            ? rtrim(rtrim(number_format($kilobytes / 1024, 1, ',', ''), '0'), ',').' MB'
            : "{$kilobytes} KB";
    }
}
