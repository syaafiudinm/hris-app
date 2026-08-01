<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Kontrak PKWT — {{ $employee->full_name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #0d1b2a; margin: 0; }
        .header { border-bottom: 2px solid #184f95; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 16px; margin: 0; color: #184f95; }
        .header p { margin: 3px 0 0; font-size: 9px; color: #55677d; }
        table.identity { margin-bottom: 14px; }
        table.identity td { padding: 2px 0; font-size: 9px; }
        table.identity .label { color: #55677d; width: 140px; }
        h2 { font-size: 11px; margin: 18px 0 6px; text-transform: uppercase; color: #184f95; }
        .content { font-size: 10px; line-height: 1.7; }
        .content ol { padding-left: 18px; }
        .content ol li { margin-bottom: 6px; }
        .highlight {
            margin-top: 14px; background: #f0f6fe; padding: 10px 12px;
            border-left: 3px solid #184f95; font-size: 9px;
        }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { font-size: 9px; color: #55677d; height: 70px; vertical-align: top; width: 50%; }
        .note { margin-top: 20px; font-size: 8px; color: #8fa1b6; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Perjanjian Kerja Waktu Tertentu (PKWT)</h1>
        <p>Dicetak {{ $generatedAt }}</p>
    </div>

    <table class="identity">
        <tr><td class="label">Nama Karyawan</td><td>: {{ $employee->full_name }}</td></tr>
        <tr><td class="label">NIK</td><td>: {{ $employee->nik }}</td></tr>
        <tr><td class="label">Jabatan</td><td>: {{ $employee->position ?? '-' }}</td></tr>
        <tr><td class="label">Divisi</td><td>: {{ $employee->department?->name ?? '-' }}</td></tr>
        <tr><td class="label">Entitas Kerja</td><td>: {{ $employee->employmentType?->name }}</td></tr>
        <tr><td class="label">Durasi Kontrak</td><td>: {{ $employee->employmentType?->duration_months }} bulan</td></tr>
        <tr><td class="label">Tanggal Mulai</td><td>: {{ $employee->contract_start?->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="label">Tanggal Berakhir</td><td>: {{ $employee->contract_end?->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="content">
        <h2>Ketentuan Umum</h2>
        <ol>
            <li>Perjanjian kerja ini berlaku untuk waktu tertentu selama <strong>{{ $employee->employmentType?->duration_months }} bulan</strong> dan tidak dapat diakhiri sebelum jangka waktu berakhir kecuali atas kesepakatan bersama.</li>
            <li>Karyawan berhak atas <strong>cuti tahunan sebanyak {{ $employee->employmentType?->annual_leave_quota ?? 0 }} hari</strong> sesuai entitas kerja yang berlaku.</li>
            <li>Karyawan termasuk dalam <strong>program BPJS Kesehatan dan Ketenagakerjaan</strong> sesuai peraturan perundang-undangan yang berlaku.</li>
            <li>Karyawan wajib mengikuti seluruh peraturan, tata tertib, dan kebijakan perusahaan selama masa kontrak berlaku.</li>
            <li>Perpanjangan kontrak akan dibahas paling lambat 30 hari sebelum kontrak berakhir.</li>
        </ol>

        <div class="highlight">
            <p><strong>Kompensasi:</strong> Rp {{ number_format((float) $employee->basic_salary, 0, ',', '.') }} / bulan</p>
            <p><strong>BPJS:</strong> Terdaftar (Kes 1% + JHT 2% + JP 1% dari pekerja)</p>
            <p><strong>Cuti Tahunan:</strong> {{ $employee->employmentType?->annual_leave_quota ?? 0 }} hari</p>
        </div>
    </div>

    <table class="sign">
        <tr>
            <td>Pihak Perusahaan,<br><strong>Human Capital</strong></td>
            <td>Pihak Karyawan,<br><strong>{{ $employee->full_name }}</strong></td>
        </tr>
    </table>

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem HRIS dan sah sebagai lampiran perjanjian kerja waktu tertentu (PKWT).
    </p>
</body>
</html>
