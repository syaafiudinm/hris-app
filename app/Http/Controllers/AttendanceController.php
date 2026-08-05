<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OfficeLocation;
use App\Services\AttendanceService;
use App\Services\ExportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /**
     * Rekap kehadiran untuk HR & Manager.
     */
    public function index(Request $request): Response
    {
        $records = $this->filtered($request)
            ->with(['employee.department', 'employee.employmentType'])
            ->orderByDesc('date')
            ->orderBy('clock_in')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Attendance $record) => [
                'id' => $record->id,
                'date' => $record->date->translatedFormat('d M Y'),
                'employee' => $record->employee?->full_name,
                'nik' => $record->employee?->nik,
                'department' => $record->employee?->department?->name,
                'category' => $record->employee?->employmentType?->category,
                'clockIn' => $record->clock_in?->format('H:i'),
                'clockOut' => $record->clock_out?->format('H:i'),
                'status' => $record->status,
                'lateMinutes' => $record->late_minutes,
                'workHours' => round($record->work_minutes / 60, 1),
                'isFakeGps' => $record->is_fake_gps,
                'method' => $record->clock_in_method,
                'methodLabel' => Attendance::METHOD_LABELS[$record->clock_in_method] ?? $record->clock_in_method,
                'verification' => $record->verification_status,
                'verificationLabel' => Attendance::VERIFICATION_LABELS[$record->verification_status] ?? $record->verification_status,
                'verificationNote' => $record->verification_note,
                'note' => $record->clock_in_note,
                'office' => $record->clock_in_office,
                'distance' => $record->clock_in_distance,
                'isOutsideRadius' => $record->is_outside_radius,
                'hasPhoto' => (bool) $record->clock_in_photo,
            ]);

        $range = $this->range($request);

        return Inertia::render('Attendance/Index', [
            'records' => $records,
            'filters' => $this->filterValues($request),
            'options' => [
                'departments' => Department::orderBy('name')->get(['id', 'name'])->all(),
                'statuses' => ['present', 'late', 'absent', 'leave'],
                'categories' => ['probation', 'pkwt', 'mitra'],
                'methods' => AttendanceService::METHODS,
                'methodLabels' => Attendance::METHOD_LABELS,
                'verificationLabels' => Attendance::VERIFICATION_LABELS,
            ],
            'stats' => $this->stats($request, $range),
        ]);
    }

    /**
     * Portal absensi mandiri (self-service).
     */
    public function me(Request $request): Response
    {
        $employee = $this->currentEmployee($request);
        $today = CarbonImmutable::today();

        $todayRecord = Attendance::where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        return Inertia::render('Attendance/Me', [
            'employee' => [
                'name' => $employee->full_name,
                'nik' => $employee->nik,
                'type' => $employee->employmentType?->name,
                'category' => $employee->employmentType?->category,
            ],
            'today' => [
                'date' => $today->translatedFormat('l, d F Y'),
                'clockIn' => $todayRecord?->clock_in?->format('H:i'),
                'clockOut' => $todayRecord?->clock_out?->format('H:i'),
                'status' => $todayRecord?->status,
                'lateMinutes' => $todayRecord?->late_minutes ?? 0,
                'isFakeGps' => (bool) $todayRecord?->is_fake_gps,
                'workHours' => $todayRecord ? round($todayRecord->work_minutes / 60, 1) : 0,
                'method' => $todayRecord?->clock_in_method,
                'methodLabel' => $todayRecord ? (Attendance::METHOD_LABELS[$todayRecord->clock_in_method] ?? null) : null,
                'verification' => $todayRecord?->verification_status,
                'verificationLabel' => $todayRecord ? (Attendance::VERIFICATION_LABELS[$todayRecord->verification_status] ?? null) : null,
                'verificationNote' => $todayRecord?->verification_note,
            ],
            'offices' => OfficeLocation::where('is_active', true)
                ->get(['name', 'latitude', 'longitude', 'radius_meters'])
                ->all(),
            'history' => Attendance::where('employee_id', $employee->id)
                ->orderByDesc('date')
                ->limit(10)
                ->get()
                ->map(fn (Attendance $record) => [
                    'id' => $record->id,
                    'date' => $record->date->translatedFormat('d M Y'),
                    'clockIn' => $record->clock_in?->format('H:i'),
                    'clockOut' => $record->clock_out?->format('H:i'),
                    'status' => $record->status,
                    'workHours' => round($record->work_minutes / 60, 1),
                    'method' => $record->clock_in_method,
                    'verification' => $record->verification_status,
                ])
                ->all(),
        ]);
    }

    /**
     * Clock-in dua opsi.
     *
     * - `live`   : kamera langsung, wajib berada di dalam radius kantor.
     *              Langsung sah tanpa persetujuan.
     * - `upload` : unggah foto dari perangkat disertai titik GPS. Dipakai
     *              saat kamera browser tidak tersedia atau saat bekerja di
     *              luar kantor, sehingga radius tidak diblokir — namun
     *              absensi masuk antrean verifikasi HR.
     */
    public function clockIn(Request $request): RedirectResponse
    {
        $method = $request->string('method')->toString() ?: 'live';

        $payload = $request->validate([
            'method' => ['nullable', Rule::in(AttendanceService::METHODS)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'is_mock_location' => ['nullable', 'boolean'],
            // Kamera langsung mengirim data URL, mode unggah mengirim berkas.
            'photo' => [Rule::requiredIf($method === 'live'), 'nullable', 'string'],
            'photo_file' => [Rule::requiredIf($method === 'upload'), 'nullable', 'image', 'max:5120'],
            'note' => [Rule::requiredIf($method === 'upload'), 'nullable', 'string', 'max:500'],
        ]);

        $employee = $this->currentEmployee($request);
        $today = CarbonImmutable::today();

        $existing = Attendance::where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        if ($existing?->clock_in) {
            return back()->with('error', 'Anda sudah melakukan clock-in hari ini.');
        }

        $geofence = $this->attendance->resolveGeofence(
            (float) $payload['latitude'],
            (float) $payload['longitude'],
        );

        // Hanya mode kamera langsung yang dikunci radius; mode unggah
        // mencatat jaraknya lalu menyerahkan keputusan ke HR.
        if ($method === 'live' && ! $geofence['inside']) {
            throw ValidationException::withMessages([
                'latitude' => sprintf(
                    'Anda berada %s meter dari %s — di luar radius absensi. Gunakan opsi unggah foto bila memang bekerja di luar kantor.',
                    number_format($geofence['distance'] ?? 0, 0, ',', '.'),
                    $geofence['location']?->name ?? 'lokasi kantor',
                ),
            ]);
        }

        $fakeGpsReasons = $this->attendance->detectFakeGps($employee, $payload);

        $photoPath = $method === 'upload'
            ? $this->attendance->storeUploadedPhoto($request->file('photo_file'), $employee)
            : $this->attendance->storePhoto((string) $payload['photo'], $employee);

        if (! $photoPath) {
            throw ValidationException::withMessages([
                'photo' => 'Foto absensi tidak valid. Ulangi pengambilan atau pilih berkas lain.',
            ]);
        }

        $now = CarbonImmutable::now();
        $evaluation = $this->attendance->evaluateClockIn($now);

        Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today->toDateString()],
            [
                'clock_in' => $now,
                'clock_in_lat' => $payload['latitude'],
                'clock_in_long' => $payload['longitude'],
                'clock_in_photo' => $photoPath,
                'clock_in_method' => $method,
                'clock_in_distance' => $geofence['distance'] !== null ? (int) round($geofence['distance']) : null,
                'clock_in_office' => $geofence['location']?->name,
                'is_outside_radius' => ! $geofence['inside'],
                'clock_in_note' => $payload['note'] ?? null,
                'verification_status' => $method === 'upload' ? 'pending' : 'auto',
                'is_fake_gps' => $fakeGpsReasons !== [],
                'status' => $evaluation['status'],
                'late_minutes' => $evaluation['lateMinutes'],
            ],
        );

        if ($fakeGpsReasons !== []) {
            return back()->with(
                'error',
                'Clock-in tercatat namun ditandai untuk verifikasi HR: '.implode(' ', $fakeGpsReasons),
            );
        }

        if ($method === 'upload') {
            return back()->with(
                'success',
                "Clock-in pukul {$now->format('H:i')} terkirim. Foto unggahan menunggu verifikasi HR.",
            );
        }

        $message = $evaluation['lateMinutes'] > 0
            ? "Clock-in tercatat pukul {$now->format('H:i')} — terlambat {$evaluation['lateMinutes']} menit."
            : "Clock-in tercatat pukul {$now->format('H:i')}. Selamat bekerja.";

        return back()->with('success', $message);
    }

    /**
     * Keputusan HR atas absensi mode unggah.
     */
    public function verify(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($attendance->verification_status !== 'pending') {
            return back()->with('error', 'Absensi ini tidak sedang menunggu verifikasi.');
        }

        $this->attendance->verify(
            $attendance,
            $data['decision'],
            (int) $request->user()->id,
            $data['note'] ?? null,
        );

        return back()->with(
            'success',
            $data['decision'] === 'approved'
                ? 'Absensi disetujui dan dihitung sebagai hari hadir.'
                : 'Absensi ditolak — hari tersebut dicatat sebagai tidak hadir.',
        );
    }

    /**
     * Foto absensi disimpan di disk privat. Pemilik boleh melihat fotonya
     * sendiri; HR dan manager divisi terkait boleh memverifikasi.
     */
    public function photo(Request $request, Attendance $attendance): HttpResponse
    {
        $user = $request->user();
        $viewerEmployeeId = $user?->employee?->id;

        $allowed = $user?->isSuperAdmin()
            || $attendance->employee_id === $viewerEmployeeId
            || ($user?->role === 'manager'
                && $attendance->employee?->department_id === $user->employee?->department_id);

        abort_if(! $allowed, 403, 'Anda tidak berhak melihat foto absensi ini.');
        abort_if(! $attendance->clock_in_photo, 404, 'Absensi ini tidak memiliki foto.');

        $disk = Storage::disk(AttendanceService::PHOTO_DISK);

        abort_if(! $disk->exists($attendance->clock_in_photo), 404, 'Berkas foto tidak ditemukan.');

        return $disk->response($attendance->clock_in_photo);
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);
        $today = CarbonImmutable::today();

        $record = Attendance::where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        if (! $record?->clock_in) {
            return back()->with('error', 'Belum ada clock-in hari ini.');
        }

        if ($record->clock_out) {
            return back()->with('error', 'Anda sudah melakukan clock-out hari ini.');
        }

        $now = CarbonImmutable::now();

        $record->update([
            'clock_out' => $now,
            'work_minutes' => (int) $record->clock_in->diffInMinutes($now),
        ]);

        return back()->with('success', "Clock-out tercatat pukul {$now->format('H:i')}.");
    }

    /**
     * Laporan absensi harian/bulanan.
     */
    public function export(Request $request, ExportService $exporter)
    {
        $rows = $this->filtered($request)
            ->with(['employee.department', 'employee.employmentType'])
            ->orderByDesc('date')
            ->get()
            ->map(fn (Attendance $record) => [
                $record->date->format('d/m/Y'),
                $record->employee?->nik,
                $record->employee?->full_name,
                $record->employee?->department?->name,
                $record->employee?->employmentType?->name,
                $record->clock_in?->format('H:i') ?? '-',
                $record->clock_out?->format('H:i') ?? '-',
                ucfirst($record->status),
                $record->late_minutes,
                round($record->work_minutes / 60, 1),
                $record->is_fake_gps ? 'Ya' : 'Tidak',
                Attendance::METHOD_LABELS[$record->clock_in_method] ?? $record->clock_in_method,
                Attendance::VERIFICATION_LABELS[$record->verification_status] ?? $record->verification_status,
                $record->is_outside_radius ? 'Ya' : 'Tidak',
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Laporan Absensi',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Laporan Absensi',
            headings: [
                'Tanggal', 'NIK', 'Nama', 'Divisi', 'Entitas Kerja',
                'Clock In', 'Clock Out', 'Status', 'Telat (menit)', 'Jam Kerja', 'Flag Fake GPS',
                'Metode Absen', 'Verifikasi', 'Di Luar Radius',
            ],
            rows: $rows,
            filters: $this->filterValues($request),
        );
    }

    /**
     * Laporan keterlambatan.
     */
    public function exportLate(Request $request, ExportService $exporter)
    {
        $rows = $this->filtered($request)
            ->where('status', 'late')
            ->with(['employee.department'])
            ->orderByDesc('late_minutes')
            ->get()
            ->map(fn (Attendance $record) => [
                $record->date->format('d/m/Y'),
                $record->employee?->nik,
                $record->employee?->full_name,
                $record->employee?->department?->name,
                $record->clock_in?->format('H:i') ?? '-',
                $record->late_minutes,
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Laporan Keterlambatan',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Laporan Keterlambatan',
            headings: ['Tanggal', 'NIK', 'Nama', 'Divisi', 'Clock In', 'Telat (menit)'],
            rows: $rows,
            filters: $this->filterValues($request),
        );
    }

    /**
     * Timesheet jam kerja mitra — dasar kalkulasi hourly/daily rate.
     */
    public function exportMitraTimesheet(Request $request, ExportService $exporter)
    {
        $range = $this->range($request);

        $rows = Employee::query()
            ->whereHas('employmentType', fn (Builder $query) => $query->where('category', 'mitra'))
            ->with('department')
            ->withSum(
                ['attendances as total_minutes' => fn ($query) => $query->whereBetween('date', [$range['from'], $range['to']])],
                'work_minutes',
            )
            ->withCount(
                ['attendances as present_days' => fn ($query) => $query->whereBetween('date', [$range['from'], $range['to']])->whereIn('status', ['present', 'late'])],
            )
            ->orderBy('full_name')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->nik,
                $employee->full_name,
                $employee->department?->name,
                (int) $employee->present_days,
                round(((int) $employee->total_minutes) / 60, 1),
            ])
            ->all();

        return $exporter->download(
            $request,
            module: 'Timesheet Mitra',
            format: (string) $request->string('format', 'xlsx'),
            title: 'Timesheet Jam Kerja Mitra',
            headings: ['NIK', 'Nama Mitra', 'Divisi', 'Hari Kerja', 'Total Jam'],
            rows: $rows,
            filters: ['periode' => "{$range['from']} s/d {$range['to']}"],
        );
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee()->with('employmentType')->first();

        abort_if(! $employee, 403, 'Akun Anda belum tertaut ke data tenaga kerja.');

        return $employee;
    }

    /**
     * @return array{from: string, to: string}
     */
    private function range(Request $request): array
    {
        $to = $request->date('to') ?? CarbonImmutable::today();
        $from = $request->date('from') ?? CarbonImmutable::parse($to)->subDays(29);

        return [
            'from' => CarbonImmutable::parse($from)->toDateString(),
            'to' => CarbonImmutable::parse($to)->toDateString(),
        ];
    }

    private function filtered(Request $request): Builder
    {
        $range = $this->range($request);
        $user = $request->user();

        return Attendance::query()
            ->whereBetween('date', [$range['from'], $range['to']])
            // Manager hanya melihat divisinya sendiri; HR melihat semua.
            ->when(
                $user && ! $user->isSuperAdmin(),
                fn (Builder $query) => $query->whereHas(
                    'employee',
                    fn (Builder $inner) => $inner->where('department_id', $user->employee?->department_id),
                ),
            )
            ->when($request->integer('department_id'), fn (Builder $query, int $id) => $query->whereHas('employee', fn (Builder $inner) => $inner->where('department_id', $id)))
            ->when($request->string('category')->toString(), fn (Builder $query, string $category) => $query->whereHas('employee.employmentType', fn (Builder $inner) => $inner->where('category', $category)))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->boolean('fake_gps_only'), fn (Builder $query) => $query->where('is_fake_gps', true))
            ->when($request->string('method')->toString(), fn (Builder $query, string $method) => $query->where('clock_in_method', $method))
            ->when($request->string('verification')->toString(), fn (Builder $query, string $verification) => $query->where('verification_status', $verification))
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $inner) => $inner->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")));
    }

    /**
     * @param  array{from: string, to: string}  $range
     * @return array<string, mixed>
     */
    private function stats(Request $request, array $range): array
    {
        $counts = (clone $this->filtered($request))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'leave' => (int) ($counts['leave'] ?? 0),
            'fakeGps' => (clone $this->filtered($request))->where('is_fake_gps', true)->count(),
            'pendingVerification' => (clone $this->filtered($request))->where('verification_status', 'pending')->count(),
            'uploadMode' => (clone $this->filtered($request))->where('clock_in_method', 'upload')->count(),
            'rangeLabel' => CarbonImmutable::parse($range['from'])->translatedFormat('d M').' – '.CarbonImmutable::parse($range['to'])->translatedFormat('d M Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterValues(Request $request): array
    {
        $range = $this->range($request);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'search' => $request->string('search')->toString() ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'fake_gps_only' => $request->boolean('fake_gps_only'),
            'method' => $request->string('method')->toString() ?: null,
            'verification' => $request->string('verification')->toString() ?: null,
        ];
    }
}
