<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Offering Letter — {{ $applicant->full_name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #0d1b2a; margin: 0; }
        .header { border-bottom: 2px solid #184f95; padding-bottom: 10px; margin-bottom: 18px; }
        .header h1 { font-size: 16px; margin: 0; color: #184f95; }
        .header p { margin: 3px 0 0; font-size: 9px; color: #55677d; }
        .label { font-size: 9px; color: #55677d; margin-bottom: 2px; }
        .info { margin-bottom: 12px; line-height: 1.6; }
        h2 { font-size: 11px; margin: 20px 0 8px; text-transform: uppercase; color: #184f95; }
        .content { font-size: 10px; line-height: 1.7; }
        .highlight {
            margin-top: 16px; background: #f0f6fe; padding: 12px 14px;
            border-left: 3px solid #184f95;
        }
        .highlight p { margin: 3px 0; }
        .sign { margin-top: 40px; width: 100%; }
        .sign td { font-size: 9px; color: #55677d; height: 70px; vertical-align: top; width: 50%; }
        .note { margin-top: 20px; font-size: 8px; color: #8fa1b6; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Offering Letter</h1>
        <p>Dicetak {{ $generatedAt }}</p>
    </div>

    <div class="info">
        <p class="label">Kepada Yth.</p>
        <p><strong>{{ $applicant->full_name }}</strong></p>
        <p>{{ $applicant->email }}@if ($applicant->phone) · {{ $applicant->phone }}@endif</p>
    </div>

    <div class="content">
        <p>Dengan hormat,</p>

        <p>
            Berdasarkan hasil proses seleksi, kami dengan senang hati memberitahukan bahwa
            Anda telah kami terima untuk posisi yang Anda lamar pada lowongan berikut:
        </p>

        <div class="highlight">
            <p><strong>Posisi:</strong> {{ $vacancy?->title }}</p>
            <p><strong>Divisi:</strong> {{ $vacancy?->department?->name ?? '-' }}</p>
            <p><strong>Kategori:</strong> {{ $vacancy?->offered_category === 'probation' ? 'Probation Track' : ($vacancy?->offered_category === 'pkwt' ? 'Full-time PKWT' : 'Mitra / Freelance') }}</p>
            <p><strong>Lokasi:</strong> {{ $vacancy?->location ?? '-' }}</p>
        </div>

        <h2>Ketentuan Umum</h2>

        <p>
            Surat ini merupakan penawaran resmi untuk bergabung dengan tim kami.
            Detail kompensasi, tunjangan, dan ketentuan kontrak kerja akan disampaikan
            secara terpisah pada saat penandatanganan kontrak.
        </p>

        <p>
            Kami mengharapkan konfirmasi penerimaan Anda paling lambat <strong>7 (tujuh) hari kerja</strong>
            sejak tanggal surat ini diterbitkan. Apabila dalam jangka waktu tersebut kami belum
            menerima konfirmasi, penawaran ini dianggap batal.
        </p>

        <p>
            Kami sangat antusias menyambut Anda bergabung dengan tim kami dan yakin bahwa keahlian
            serta pengalaman Anda akan menjadi kontribusi yang berharga bagi perusahaan.
        </p>
    </div>

    <table class="sign">
        <tr>
            <td>
                Hormat kami,<br>
                <strong>Human Capital Department</strong>
            </td>
            <td>
                Menyetujui,<br>
                <strong>{{ $applicant->full_name }}</strong>
            </td>
        </tr>
    </table>

    <p class="note">
        Dokumen ini dihasilkan otomatis oleh sistem HRIS & ATS.
        Pertanyaan terkait penawaran ini dapat disampaikan ke tim Human Capital.
    </p>
</body>
</html>
