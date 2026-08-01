<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Payment Voucher {{ $employee?->full_name }} — {{ $periodLabel }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #0d1b2a; margin: 0; }
        .header { border-bottom: 2px solid #184f95; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 15px; margin: 0; color: #184f95; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #55677d; }
        .identity td { padding: 2px 0; font-size: 9px; }
        .identity .label { color: #55677d; width: 130px; }
        h2 { font-size: 10px; margin: 16px 0 5px; text-transform: uppercase; color: #184f95; }
        table.amounts { width: 100%; border-collapse: collapse; }
        table.amounts td { padding: 4px 6px; border-bottom: 1px solid #e2ecf8; }
        table.amounts td.value { text-align: right; }
        .total td { font-weight: bold; border-top: 1px solid #184f95; border-bottom: none; }
        .net {
            margin-top: 14px; background: #f0f6fe; padding: 10px 12px;
            border-left: 3px solid #184f95;
        }
        .net span { font-size: 9px; color: #55677d; display: block; }
        .net strong { font-size: 15px; }
        .sign { margin-top: 30px; width: 100%; }
        .sign td { font-size: 9px; color: #55677d; height: 60px; vertical-align: top; width: 50%; }
        .note { margin-top: 14px; font-size: 8px; color: #8fa1b6; line-height: 1.5; }
    </style>
</head>
<body>
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

    <div class="header">
        <h1>Payment Voucher Mitra</h1>
        <p>Periode {{ $periodLabel }} &middot; Dicetak {{ $generatedAt }}</p>
    </div>

    <table class="identity">
        <tr><td class="label">Nama Mitra</td><td>: {{ $employee?->full_name }}</td></tr>
        <tr><td class="label">ID Mitra</td><td>: {{ $employee?->nik }}</td></tr>
        <tr><td class="label">Bidang Kerja</td><td>: {{ $employee?->position ?? '-' }}</td></tr>
        <tr>
            <td class="label">Skema Pembayaran</td>
            <td>: {{ $schemaLabels[$schema?->schema_type] ?? '-' }}
                @if ($schema && $schema->unit_label)
                    (Rp {{ number_format((float) $schema->rate_per_unit, 0, ',', '.') }} / {{ $schema->unit_label }})
                @endif
            </td>
        </tr>
        <tr><td class="label">Skema Pajak</td><td>: {{ $taxLabels[$schema?->tax_scheme] ?? '-' }}</td></tr>
    </table>

    <h2>Rincian Pembayaran</h2>
    <table class="amounts">
        <tr>
            <td>Nilai Kompensasi Dasar</td>
            <td class="value">Rp {{ number_format((float) $payroll->basic_amount, 0, ',', '.') }}</td>
        </tr>
        @if ((float) $payroll->allowance_amount > 0)
            <tr>
                <td>Tunjangan / Bonus</td>
                <td class="value">Rp {{ number_format((float) $payroll->allowance_amount, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>Total Bruto</td>
            <td class="value">Rp {{ number_format((float) $payroll->gross_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Pemotongan Pajak ({{ rtrim(rtrim(number_format((float) ($schema?->custom_tax_percentage ?? 0), 2, ',', '.'), '0'), ',') }}%)</td>
            <td class="value">Rp {{ number_format((float) $payroll->pph_deduction, 0, ',', '.') }}</td>
        </tr>
        @if ((float) $payroll->other_deduction > 0)
            <tr>
                <td>Penalti / Potongan Lain</td>
                <td class="value">Rp {{ number_format((float) $payroll->other_deduction, 0, ',', '.') }}</td>
            </tr>
        @endif
    </table>

    <div class="net">
        <span>Jumlah Dibayarkan</span>
        <strong>Rp {{ number_format((float) $payroll->net_payout, 0, ',', '.') }}</strong>
    </div>

    <p class="note">
        Mitra tidak termasuk kepesertaan BPJS perusahaan dan tidak memiliki kuota cuti tahunan
        sesuai perjanjian kemitraan.
    </p>

    <table class="sign">
        <tr>
            <td>Disetujui oleh,<br>Human Capital</td>
            <td>Diterima oleh,<br>{{ $employee?->full_name }}</td>
        </tr>
    </table>
</body>
</html>
