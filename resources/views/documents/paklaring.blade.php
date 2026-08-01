<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Keterangan Kerja — {{ $employee?->full_name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #0d1b2a; margin: 0; line-height: 1.7; }
        .kop {
            border-bottom: 2px solid #184f95; padding-bottom: 12px; margin-bottom: 22px;
        }
        .kop h1 { font-size: 15px; margin: 0; color: #184f95; letter-spacing: .5px; }
        .kop p { margin: 3px 0 0; font-size: 9px; color: #55677d; }
        .judul { text-align: center; margin-bottom: 22px; }
        .judul h2 {
            font-size: 13px; margin: 0; text-transform: uppercase;
            text-decoration: underline; letter-spacing: 1px;
        }
        .judul p { margin: 4px 0 0; font-size: 10px; color: #55677d; }
        table.identitas { margin: 14px 0 14px 24px; }
        table.identitas td { padding: 2px 0; vertical-align: top; }
        table.identitas td.label { width: 130px; }
        table.identitas td.pemisah { width: 14px; }
        .ttd { margin-top: 40px; width: 100%; }
        .ttd td { width: 50%; vertical-align: top; }
        .ttd .ruang { height: 62px; }
        .catatan {
            margin-top: 36px; padding-top: 10px; border-top: 1px solid #e2ecf8;
            font-size: 8px; color: #8fa1b6; line-height: 1.5;
        }
    </style>
</head>
<body>
    @php
        // Kalimat penutup disesuaikan penyebab berakhirnya hubungan kerja.
        // Untuk PHK, penilaian kinerja sengaja tidak dicantumkan.
        $penyebab = match ($exit->exit_type) {
            'resign' => 'mengundurkan diri atas permintaan sendiri',
            'contract_end' => 'berakhirnya masa perjanjian kerja',
            'termination' => 'berakhirnya hubungan kerja',
            'retirement' => 'memasuki masa pensiun',
        };
        $adaPenilaian = in_array($exit->exit_type, ['resign', 'contract_end', 'retirement'], true);
    @endphp

    <div class="kop">
        <h1>{{ config('app.name') }}</h1>
        <p>Divisi Human Capital</p>
    </div>

    <div class="judul">
        <h2>Surat Keterangan Kerja</h2>
        <p>Nomor: {{ $exit->paklaring_number }}</p>
    </div>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

    <table class="identitas">
        <tr>
            <td class="label">Nama</td><td class="pemisah">:</td>
            <td><strong>{{ $employee?->full_name }}</strong></td>
        </tr>
        <tr>
            <td class="label">Nomor Induk</td><td class="pemisah">:</td>
            <td>{{ $employee?->nik }}</td>
        </tr>
        <tr>
            <td class="label">Jabatan Terakhir</td><td class="pemisah">:</td>
            <td>{{ $employee?->position ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Divisi</td><td class="pemisah">:</td>
            <td>{{ $employee?->department?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Status Kerja</td><td class="pemisah">:</td>
            <td>{{ $employee?->employmentType?->name ?? '-' }}</td>
        </tr>
    </table>

    <p>
        Adalah benar telah bekerja di {{ config('app.name') }} terhitung sejak
        <strong>{{ $employee?->join_date?->translatedFormat('d F Y') }}</strong>
        sampai dengan
        <strong>{{ $exit->last_working_date->translatedFormat('d F Y') }}</strong>,
        dengan masa kerja <strong>{{ $tenure['label'] }}</strong>.
    </p>

    <p>
        Yang bersangkutan tidak lagi bekerja pada perusahaan kami terhitung sejak
        tanggal tersebut di atas karena {{ $penyebab }}.
    </p>

    @if ($adaPenilaian)
        <p>
            Selama bekerja, yang bersangkutan telah menjalankan tugas dan
            tanggung jawabnya dengan baik serta tidak meninggalkan kewajiban
            apa pun terhadap perusahaan.
        </p>
    @else
        <p>
            Yang bersangkutan telah menyelesaikan kewajibannya terhadap perusahaan
            sampai dengan tanggal berakhirnya hubungan kerja.
        </p>
    @endif

    <p>
        Demikian surat keterangan ini dibuat dengan sebenarnya agar dapat
        dipergunakan sebagaimana mestinya.
    </p>

    <table class="ttd">
        <tr>
            <td></td>
            <td>
                {{ $employee?->department?->location ?? 'Jakarta' }}, {{ $issuedAt }}<br>
                Hormat kami,
                <div class="ruang"></div>
                <strong>{{ $exit->processedBy?->full_name ?? 'Human Capital' }}</strong><br>
                {{ $exit->processedBy?->position ?? 'Divisi Human Capital' }}
            </td>
        </tr>
    </table>

    <p class="catatan">
        Surat ini diterbitkan oleh sistem HRIS dan dapat dicetak ulang sewaktu-waktu
        dengan nomor yang sama. Keaslian dokumen dapat diverifikasi ke Divisi Human
        Capital dengan menyebutkan nomor surat di atas.
    </p>
</body>
</html>
