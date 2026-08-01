<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Perjanjian Kemitraan — {{ $employee->full_name }}</title>
    @php
        $schemaLabels = [
            'fixed_project' => 'Fixed Project Fee',
            'hourly' => 'Hourly Rate',
            'daily' => 'Daily Rate',
            'milestone' => 'Deliverable / Milestone',
            'unit' => 'Unit / Output',
        ];
        $taxLabels = [
            'pph21_berkesinambungan' => 'PPh 21 Bukan Pegawai (Berkesinambungan)',
            'pph21_tidak_berkesinambungan' => 'PPh 21 Bukan Pegawai (Tidak Berkesinambungan)',
            'pph23' => 'PPh 23',
            'bebas_pajak' => 'Bebas Pajak',
        ];
    @endphp
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
        <h1>Perjanjian Kemitraan</h1>
        <p>Dicetak {{ $generatedAt }}</p>
    </div>

    <table class="identity">
        <tr><td class="label">Nama Mitra</td><td>: {{ $employee->full_name }}</td></tr>
        <tr><td class="label">ID Mitra</td><td>: {{ $employee->nik }}</td></tr>
        <tr><td class="label">Bidang Kerja</td><td>: {{ $employee->position ?? '-' }}</td></tr>
        <tr><td class="label">Divisi</td><td>: {{ $employee->department?->name ?? '-' }}</td></tr>
        <tr><td class="label">Tanggal Mulai</td><td>: {{ $employee->contract_start?->translatedFormat('d F Y') }}</td></tr>
        @if ($employee->contract_end)
            <tr><td class="label">Tanggal Berakhir</td><td>: {{ $employee->contract_end->translatedFormat('d F Y') }}</td></tr>
        @endif
        @if ($schema)
            <tr><td class="label">Skema Pembayaran</td><td>: {{ $schemaLabels[$schema->schema_type] ?? '-' }}</td></tr>
            <tr><td class="label">Tarif</td><td>: Rp {{ number_format((float) $schema->rate_per_unit, 0, ',', '.') }} / {{ $schema->unit_label }}</td></tr>
            <tr><td class="label">Skema Pajak</td><td>: {{ $taxLabels[$schema->tax_scheme] ?? '-' }}</td></tr>
        @endif
    </table>

    <div class="content">
        <h2>Ketentuan Kemitraan</h2>
        <ol>
            <li>Hubungan antara perusahaan dan mitra bersifat kemitraan, bukan hubungan kerja sebagaimana dimaksud dalam peraturan ketenagakerjaan.</li>
            <li>Mitra <strong>tidak termasuk</strong> dalam program BPJS perusahaan dan tidak memiliki kuota cuti tahunan.</li>
            <li>Pembayaran dilakukan berdasarkan skema yang telah disepakati di atas.</li>
            <li>Mitra wajib mematuhi ketentuan kerahasiaan dan kode etik yang berlaku di lingkungan perusahaan.</li>
            <li>Perjanjian ini dapat diakhiri oleh salah satu pihak dengan pemberitahuan tertulis 14 hari sebelumnya.</li>
        </ol>

        @if ($schema)
            <div class="highlight">
                <p><strong>Skema:</strong> {{ $schemaLabels[$schema->schema_type] ?? '-' }}</p>
                <p><strong>Tarif:</strong> Rp {{ number_format((float) $schema->rate_per_unit, 0, ',', '.') }} / {{ $schema->unit_label }}</p>
                <p><strong>Pajak:</strong> {{ $taxLabels[$schema->tax_scheme] ?? '-' }} ({{ rtrim(rtrim(number_format((float) ($schema->custom_tax_percentage ?? 0), 2, ',', '.'), '0'), ',') }}%)</p>
                <p><strong>BPJS:</strong> Tidak berlaku</p>
                <p><strong>Cuti Tahunan:</strong> Tidak berlaku</p>
            </div>
        @endif
    </div>

    <table class="sign">
        <tr>
            <td>Pihak Perusahaan,<br><strong>Human Capital</strong></td>
            <td>Pihak Mitra,<br><strong>{{ $employee->full_name }}</strong></td>
        </tr>
    </table>

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem HRIS dan sah sebagai lampiran perjanjian kemitraan.
    </p>
</body>
</html>
