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
        table.amounts td.rincian { font-size: 8px; color: #8fa1b6; padding-top: 0; border-bottom: 1px solid #e2ecf8; }
        .total td { font-weight: bold; border-top: 1px solid #184f95; border-bottom: none; }
        .net {
            margin-top: 14px; background: #f0f6fe; padding: 10px 12px;
            border-left: 3px solid #184f95;
        }
        .net span { font-size: 9px; color: #55677d; display: block; }
        .net strong { font-size: 15px; }
        .kotak-bpjs {
            margin-top: 16px; border: 1px solid #cfe0f4; background: #f7fbff;
            padding: 10px 12px;
        }
        .kotak-bpjs h3 {
            font-size: 9px; margin: 0 0 6px; text-transform: uppercase; color: #184f95;
        }
        table.bpjs { width: 100%; border-collapse: collapse; font-size: 8px; }
        table.bpjs th {
            text-align: right; color: #55677d; font-weight: normal;
            padding: 2px 4px; border-bottom: 1px solid #cfe0f4;
        }
        table.bpjs th.kiri { text-align: left; }
        table.bpjs td { padding: 3px 4px; text-align: right; border-bottom: 1px solid #e6f0fa; }
        table.bpjs td.kiri { text-align: left; }
        table.bpjs tr.jumlah td { font-weight: bold; border-bottom: none; border-top: 1px solid #cfe0f4; }
        .note { margin-top: 14px; font-size: 8px; color: #8fa1b6; line-height: 1.5; }
    </style>
</head>
<body>
    @php
        $rincian = $payroll->details ?? [];
        $bpjs = $rincian['bpjs'] ?? null;
        $rupiah = fn ($angka) => 'Rp '.number_format((float) $angka, 0, ',', '.');
        $persen = fn ($angka) => rtrim(rtrim(number_format((float) $angka, 2, ',', '.'), '0'), ',').'%';
        $totalPotongan = (float) $payroll->bpjs_employee_deduction
            + (float) $payroll->pph_deduction
            + (float) $payroll->other_deduction;
    @endphp

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

    <h2>A. Penerimaan</h2>
    <table class="amounts">
        <tr>
            <td>Gaji Pokok</td>
            <td class="value">{{ $rupiah($payroll->basic_amount) }}</td>
        </tr>
        <tr>
            <td>
                Tunjangan Tetap
                @if (($rincian['allowanceRate'] ?? null))
                    <span style="color:#8fa1b6">
                        ({{ $persen($rincian['allowanceRate']) }} dari gaji pokok)
                    </span>
                @endif
            </td>
            <td class="value">{{ $rupiah($payroll->allowance_amount) }}</td>
        </tr>
        <tr>
            <td>
                Upah Lembur
                @if (($rincian['overtimeHours'] ?? 0) > 0)
                    <span style="color:#8fa1b6">
                        ({{ number_format($rincian['overtimeHours'], 1, ',', '.') }} jam
                        &times; {{ $rupiah($rincian['hourlyRate'] ?? 0) }} &times; 1,5)
                    </span>
                @else
                    <span style="color:#8fa1b6">(tidak ada lembur pada periode ini)</span>
                @endif
            </td>
            <td class="value">{{ $rupiah($payroll->overtime_amount) }}</td>
        </tr>
        <tr class="total">
            <td>Total Penerimaan (Bruto)</td>
            <td class="value">{{ $rupiah($payroll->gross_amount) }}</td>
        </tr>
    </table>

    <h2>B. Potongan</h2>
    <table class="amounts">
        <tr>
            <td>
                Iuran BPJS porsi pekerja
                <span style="color:#8fa1b6">
                    @if ($bpjs)
                        (seharusnya {{ $rupiah($bpjs['workerPortion']) }} —
                        dibayarkan perusahaan, lihat bagian C)
                    @else
                        (entitas {{ $employee?->employmentType?->name }} belum didaftarkan)
                    @endif
                </span>
            </td>
            <td class="value">{{ $rupiah($payroll->bpjs_employee_deduction) }}</td>
        </tr>
        <tr>
            <td>
                PPh 21
                <span style="color:#8fa1b6">(tarif efektif bulanan, PP 58/2023)</span>
            </td>
            <td class="value">{{ $rupiah($payroll->pph_deduction) }}</td>
        </tr>
        @if ((float) $payroll->other_deduction > 0)
            <tr>
                <td>Potongan Lain</td>
                <td class="value">{{ $rupiah($payroll->other_deduction) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>Total Potongan</td>
            <td class="value">{{ $rupiah($totalPotongan) }}</td>
        </tr>
    </table>

    <div class="net">
        <span>Gaji Diterima (Take Home Pay) = Total Penerimaan &minus; Total Potongan</span>
        <strong>{{ $rupiah($payroll->net_payout) }}</strong>
    </div>

    @if ($bpjs)
        <div class="kotak-bpjs">
            <h3>C. Iuran BPJS yang Dibayarkan Perusahaan</h3>
            <p style="margin:0 0 8px; font-size:8px; color:#55677d; line-height:1.5">
                Anda terdaftar sebagai peserta BPJS. Seluruh iuran di bawah ini
                <strong>dibayarkan perusahaan</strong> — termasuk porsi yang biasanya
                menjadi tanggungan pekerja. Karena itu potongan pada bagian B bernilai
                {{ $rupiah(0) }}. Rincian ini bukan pengurang gaji Anda.
            </p>

            <table class="bpjs">
                <thead>
                    <tr>
                        <th class="kiri">Program</th>
                        <th>Porsi Perusahaan</th>
                        <th>Porsi Pekerja<br>(ditalangi perusahaan)</th>
                        <th>Jumlah Iuran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bpjs['items'] as $item)
                        <tr>
                            <td class="kiri">{{ $item['label'] }}</td>
                            <td>
                                {{ $persen($item['companyRate']) }}<br>
                                {{ $rupiah($item['companyAmount']) }}
                            </td>
                            <td>
                                @if ($item['workerRate'] > 0)
                                    {{ $persen($item['workerRate']) }}<br>
                                    {{ $rupiah($item['workerAmount']) }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td>{{ $rupiah($item['total']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="jumlah">
                        <td class="kiri">Total ditanggung perusahaan</td>
                        <td>{{ $rupiah($bpjs['companyPortion']) }}</td>
                        <td>{{ $rupiah($bpjs['workerPortion']) }}</td>
                        <td>{{ $rupiah($bpjs['grandTotal']) }}</td>
                    </tr>
                </tbody>
            </table>

            <p style="margin:8px 0 0; font-size:8px; color:#8fa1b6">
                Dasar upah iuran {{ $rupiah($bpjs['wageBase']) }}.
                @if ($bpjs['jpBase'] < $bpjs['wageBase'])
                    Jaminan Pensiun dihitung dari batas upah
                    {{ $rupiah($bpjs['jpBase']) }} sesuai ketentuan yang berlaku.
                @endif
            </p>
        </div>
    @endif

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem HRIS dan sah tanpa tanda tangan basah.
        Pertanyaan terkait perhitungan dapat disampaikan ke tim Human Capital.
    </p>
</body>
</html>
