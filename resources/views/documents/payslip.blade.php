<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $employee?->full_name }} — {{ $periodLabel }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #0d1b2a; margin: 0; }
        .header { border-bottom: 2px solid #184f95; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 15px; margin: 0; color: #184f95; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #55677d; }
        .identity td { padding: 2px 0; font-size: 9px; }
        .identity .label { color: #55677d; width: 110px; }
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
        .note { margin-top: 14px; font-size: 8px; color: #8fa1b6; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Slip Gaji Karyawan</h1>
        <p>Periode {{ $periodLabel }} &middot; Dicetak {{ $generatedAt }}</p>
    </div>

    <table class="identity">
        <tr><td class="label">Nama</td><td>: {{ $employee?->full_name }}</td></tr>
        <tr><td class="label">NIK</td><td>: {{ $employee?->nik }}</td></tr>
        <tr><td class="label">Jabatan</td><td>: {{ $employee?->position ?? '-' }}</td></tr>
        <tr><td class="label">Divisi</td><td>: {{ $employee?->department?->name ?? '-' }}</td></tr>
        <tr><td class="label">Entitas Kerja</td><td>: {{ $employee?->employmentType?->name }}</td></tr>
    </table>

    <h2>Penerimaan</h2>
    <table class="amounts">
        <tr>
            <td>Gaji Pokok</td>
            <td class="value">Rp {{ number_format((float) $payroll->basic_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Tunjangan</td>
            <td class="value">Rp {{ number_format((float) $payroll->allowance_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Upah Lembur</td>
            <td class="value">Rp {{ number_format((float) $payroll->overtime_amount, 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Total Bruto</td>
            <td class="value">Rp {{ number_format((float) $payroll->gross_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <h2>Potongan</h2>
    <table class="amounts">
        @if ($employee?->isBpjsEligible())
            <tr>
                <td>BPJS Kesehatan &amp; Ketenagakerjaan (pekerja)</td>
                <td class="value">Rp {{ number_format((float) $payroll->bpjs_employee_deduction, 0, ',', '.') }}</td>
            </tr>
        @else
            <tr>
                <td>BPJS (tidak berlaku untuk entitas {{ $employee?->employmentType?->name }})</td>
                <td class="value">Rp 0</td>
            </tr>
        @endif
        <tr>
            <td>PPh 21 (TER PP 58/2023)</td>
            <td class="value">Rp {{ number_format((float) $payroll->pph_deduction, 0, ',', '.') }}</td>
        </tr>
        @if ((float) $payroll->other_deduction > 0)
            <tr>
                <td>Potongan Lain</td>
                <td class="value">Rp {{ number_format((float) $payroll->other_deduction, 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>Total Potongan</td>
            <td class="value">
                Rp {{ number_format((float) $payroll->bpjs_employee_deduction + (float) $payroll->pph_deduction + (float) $payroll->other_deduction, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    <div class="net">
        <span>Gaji Diterima (Take Home Pay)</span>
        <strong>Rp {{ number_format((float) $payroll->net_payout, 0, ',', '.') }}</strong>
    </div>

    @if ($employee?->isBpjsEligible())
        <p class="note">
            Kontribusi perusahaan (tidak memotong gaji Anda):
            Rp {{ number_format((float) $payroll->bpjs_company_contribution, 0, ',', '.') }}
            untuk BPJS Kesehatan &amp; Ketenagakerjaan.
        </p>
    @endif

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem HRIS dan sah tanpa tanda tangan basah.
        Pertanyaan terkait perhitungan dapat disampaikan ke tim Human Capital.
    </p>
</body>
</html>
