<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $payroll->slip_type === 'incentive' ? 'Voucher Insentif' : 'Payment Voucher' }} {{ $employee?->full_name }} — {{ $periodLabel }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #0d1b2a; margin: 0; }
        .header { border-bottom: 2px solid #184f95; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 15px; margin: 0; color: #184f95; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #55677d; }
        .identity td { padding: 2px 0; font-size: 9px; }
        .identity .label { color: #55677d; width: 140px; }
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
        .kotak {
            margin-top: 14px; border: 1px solid #cfe0f4; background: #f7fbff;
            padding: 10px 12px; font-size: 8px; color: #55677d; line-height: 1.6;
        }
        .kotak h3 { font-size: 9px; margin: 0 0 6px; text-transform: uppercase; color: #184f95; }
        table.bpjs { width: 100%; border-collapse: collapse; font-size: 8px; }
        table.bpjs th {
            text-align: right; color: #55677d; font-weight: normal;
            padding: 2px 4px; border-bottom: 1px solid #cfe0f4;
        }
        table.bpjs th.kiri { text-align: left; }
        table.bpjs td { padding: 3px 4px; text-align: right; border-bottom: 1px solid #e6f0fa; }
        table.bpjs td.kiri { text-align: left; }
        table.bpjs tr.jumlah td { font-weight: bold; border-bottom: none; border-top: 1px solid #cfe0f4; }
        .sign { margin-top: 26px; width: 100%; }
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
            'sales' => 'Kompensasi Penjualan',
        ];
        $taxLabels = [
            'pph21_berkesinambungan' => 'PPh 21 Bukan Pegawai (Berkesinambungan)',
            'pph21_tidak_berkesinambungan' => 'PPh 21 Bukan Pegawai (Tidak Berkesinambungan)',
            'pph23' => 'PPh 23',
            'bebas_pajak' => 'Bebas Pajak',
        ];

        $rincian = $payroll->details ?? [];
        $bpjs = $rincian['bpjs'] ?? null;
        $isInsentif = $payroll->slip_type === 'incentive';
        $isPenjualan = ($schema?->schema_type ?? null) === 'sales';
        $dapatBonus = ($rincian['basis'] ?? null) === 'bonus';

        $rupiah = fn ($angka) => 'Rp '.number_format((float) $angka, 0, ',', '.');
        $persen = fn ($angka) => rtrim(rtrim(number_format((float) $angka, 2, ',', '.'), '0'), ',').'%';
    @endphp

    <div class="header">
        <h1>{{ $isInsentif ? 'Voucher Insentif Penjualan' : 'Payment Voucher Mitra' }}</h1>
        <p>
            Periode {{ $periodLabel }} &middot; Dicetak {{ $generatedAt }}
            @if ($isPenjualan)
                &middot; {{ $isInsentif ? 'Slip insentif (terpisah dari slip gaji)' : 'Slip gaji (insentif pada slip terpisah)' }}
            @endif
        </p>
    </div>

    <table class="identity">
        <tr><td class="label">Nama Mitra</td><td>: {{ $employee?->full_name }}</td></tr>
        <tr><td class="label">ID Mitra</td><td>: {{ $employee?->nik }}</td></tr>
        <tr><td class="label">Bidang Kerja</td><td>: {{ $employee?->position ?? '-' }}</td></tr>
        <tr>
            <td class="label">Skema Pembayaran</td>
            <td>: {{ $schemaLabels[$schema?->schema_type] ?? '-' }}
                @if ($schema && ! $isPenjualan && $schema->unit_label)
                    ({{ $rupiah($schema->rate_per_unit) }} / {{ $schema->unit_label }})
                @endif
            </td>
        </tr>
        <tr><td class="label">Skema Pajak</td><td>: {{ $taxLabels[$schema?->tax_scheme] ?? '-' }}</td></tr>
    </table>

    @if ($isInsentif)
        {{-- ============================ SLIP INSENTIF ============================ --}}
        <h2>A. Rincian Insentif Penjualan</h2>
        <table class="amounts">
            @foreach ($rincian['lines'] ?? [] as $baris)
                <tr>
                    <td>
                        {{ $baris['product'] }}
                        <span style="color:#8fa1b6">
                            ({{ $baris['quantity'] }} unit &times; {{ $rupiah($baris['rate']) }} per unit)
                        </span>
                    </td>
                    <td class="value">{{ $rupiah($baris['subtotal']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total Insentif &mdash; {{ $rincian['totalUnits'] ?? 0 }} unit terjual</td>
                <td class="value">{{ $rupiah($rincian['incentiveAmount'] ?? 0) }}</td>
            </tr>
        </table>

        <h2>B. Potongan</h2>
        <table class="amounts">
            <tr>
                <td>
                    Pemotongan Pajak
                    <span style="color:#8fa1b6">
                        (dasar {{ $persen($rincian['taxBasePercentage'] ?? 0) }} dari insentif
                        = {{ $rupiah(($rincian['incentiveAmount'] ?? 0) * (($rincian['taxBasePercentage'] ?? 0) / 100)) }},
                        dikali tarif {{ $persen($rincian['taxRate'] ?? 0) }})
                    </span>
                </td>
                <td class="value">{{ $rupiah($rincian['taxAmount'] ?? 0) }}</td>
            </tr>
        </table>

        <div class="net">
            <span>Insentif Dibayarkan = Total Insentif &minus; Pajak</span>
            <strong>{{ $rupiah($payroll->net_payout) }}</strong>
        </div>

        <div class="kotak">
            Insentif penjualan <strong>tidak diprorata</strong> terhadap kehadiran —
            besarnya murni dari unit yang terjual. Uang makan &amp; transport atau bonus
            pencapaian dibayarkan pada <strong>slip gaji terpisah</strong> untuk periode
            yang sama. Iuran BPJS dibebankan pada slip gaji tersebut, bukan di sini,
            agar tidak terhitung dua kali.
        </div>

    @elseif ($isPenjualan)
        {{-- ========================= SLIP GAJI PENJUALAN ========================= --}}
        <h2>A. Dasar Gaji Bulanan</h2>
        <table class="amounts">
            @if ($dapatBonus)
                <tr>
                    <td>
                        <strong>Bonus Pencapaian Penjualan</strong>
                        <span style="color:#8fa1b6">
                            &mdash; {{ $rincian['totalUnits'] }} unit terjual mencapai tier
                            {{ $persen($rincian['bonusPercentage']) }} dari UMP
                            {{ $rupiah($rincian['umpReference']) }}
                        </span>
                    </td>
                    <td class="value">{{ $rupiah($rincian['monthlyBase']) }}</td>
                </tr>
                <tr>
                    <td style="color:#8fa1b6">
                        Uang Makan &amp; Transport
                        &mdash; tidak dibayarkan, digantikan bonus di atas
                    </td>
                    <td class="value" style="color:#8fa1b6">
                        ({{ $rupiah($rincian['monthlyAllowance']) }})
                    </td>
                </tr>
            @else
                <tr>
                    <td>
                        <strong>Uang Makan &amp; Transport</strong>
                        <span style="color:#8fa1b6">
                            &mdash; {{ $rincian['totalUnits'] }} unit terjual, belum mencapai
                            tier bonus
                        </span>
                    </td>
                    <td class="value">{{ $rupiah($rincian['monthlyBase']) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td>Dasar gaji sebulan penuh</td>
                <td class="value">{{ $rupiah($rincian['monthlyBase']) }}</td>
            </tr>
        </table>

        <h2>B. Prorata Kehadiran</h2>
        <table class="amounts">
            <tr>
                <td>
                    Hari hadir
                    <span style="color:#8fa1b6">
                        (dari {{ $rincian['workingDays'] }} hari kerja sebulan)
                    </span>
                </td>
                <td class="value">{{ $rincian['presentDays'] }} hari</td>
            </tr>
            <tr>
                <td>
                    Tarif harian
                    <span style="color:#8fa1b6">
                        ({{ $rupiah($rincian['monthlyBase']) }} &divide;
                        {{ $rincian['workingDays'] }} hari kerja)
                    </span>
                </td>
                <td class="value">{{ $rupiah($rincian['dailyBaseRate']) }}</td>
            </tr>
            <tr class="total">
                <td>
                    Gaji Dibayarkan
                    <span style="color:#8fa1b6; font-weight:normal">
                        ({{ $rupiah($rincian['dailyBaseRate']) }} &times;
                        {{ $rincian['presentDays'] }} hari hadir)
                    </span>
                </td>
                <td class="value">{{ $rupiah($payroll->gross_amount) }}</td>
            </tr>
        </table>

        <div class="net">
            <span>Gaji Diterima &mdash; tidak dipotong pajak; pajak hanya pada slip insentif</span>
            <strong>{{ $rupiah($payroll->net_payout) }}</strong>
        </div>

        @if ($dapatBonus)
            <div class="kotak">
                <h3>Catatan Bonus Pencapaian</h3>
                Bonus pencapaian <strong>menggantikan</strong> uang makan &amp; transport
                sebagai dasar gaji bulanan, bukan menambahnya. Pada periode ini Anda
                menjual {{ $rincian['totalUnits'] }} unit sehingga dasar gaji naik dari
                {{ $rupiah($rincian['monthlyAllowance']) }} menjadi
                {{ $rupiah($rincian['monthlyBase']) }} per bulan
                ({{ $persen($rincian['bonusPercentage']) }} dari UMP
                {{ $rupiah($rincian['umpReference']) }}).
                Insentif per unit tetap dibayarkan penuh pada slip terpisah.
            </div>
        @endif

    @else
        {{-- ======================= SKEMA MITRA LAINNYA ======================= --}}
        <h2>A. Rincian Pembayaran</h2>
        <table class="amounts">
            <tr>
                <td>Nilai Kompensasi Dasar</td>
                <td class="value">{{ $rupiah($payroll->basic_amount) }}</td>
            </tr>
            @if ((float) $payroll->allowance_amount > 0)
                <tr>
                    <td>Tunjangan / Bonus</td>
                    <td class="value">{{ $rupiah($payroll->allowance_amount) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td>Total Bruto</td>
                <td class="value">{{ $rupiah($payroll->gross_amount) }}</td>
            </tr>
        </table>

        <h2>B. Potongan</h2>
        <table class="amounts">
            <tr>
                <td>
                    Pemotongan Pajak
                    <span style="color:#8fa1b6">
                        ({{ $persen($schema?->custom_tax_percentage ?? 0) }} dari bruto)
                    </span>
                </td>
                <td class="value">{{ $rupiah($payroll->pph_deduction) }}</td>
            </tr>
            @if ((float) $payroll->other_deduction > 0)
                <tr>
                    <td>Penalti / Potongan Lain</td>
                    <td class="value">{{ $rupiah($payroll->other_deduction) }}</td>
                </tr>
            @endif
        </table>

        <div class="net">
            <span>Jumlah Dibayarkan</span>
            <strong>{{ $rupiah($payroll->net_payout) }}</strong>
        </div>
    @endif

    @if ($bpjs)
        <div class="kotak">
            <h3>Iuran BPJS yang Dibayarkan Perusahaan</h3>
            <p style="margin:0 0 8px">
                Anda didaftarkan sebagai peserta BPJS. Seluruh iuran di bawah ini
                <strong>dibayarkan perusahaan</strong>, termasuk porsi yang biasanya
                menjadi tanggungan peserta. Tidak ada pemotongan iuran dari pembayaran
                Anda.
            </p>

            <table class="bpjs">
                <thead>
                    <tr>
                        <th class="kiri">Program</th>
                        <th>Porsi Perusahaan</th>
                        <th>Porsi Peserta<br>(ditalangi perusahaan)</th>
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

            <p style="margin:8px 0 0">
                Dasar upah pelaporan iuran {{ $rupiah($bpjs['wageBase']) }}, dipakai
                karena penghasilan mitra bersifat variabel.
            </p>
        </div>
    @endif

    <table class="sign">
        <tr>
            <td>Disetujui oleh,<br>Human Capital</td>
            <td>Diterima oleh,<br>{{ $employee?->full_name }}</td>
        </tr>
    </table>

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem HRIS. Mitra tidak memiliki hubungan
        kerja tetap dengan perusahaan sesuai perjanjian kemitraan yang berlaku.
    </p>
</body>
</html>
