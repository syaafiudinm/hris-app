# Status Implementasi HRIS & ATS

Dokumen pendamping [`HRIS_ATS_System_Masterplan_v2.md`](HRIS_ATS_System_Masterplan_v2.md).
Berisi apa yang **sudah jadi**, cara menjalankannya, dan **rencana tahap berikutnya**.

| | |
| :--- | :--- |
| **Tanggal** | 1 Agustus 2026 |
| **Progres roadmap** | Fase 1 ✅ · Fase 2 ✅ · Fase 3 ⬜ |
| **Stack terpasang** | Laravel 12 (PHP 8.4) · Inertia 2 · React 19 · TypeScript · Tailwind 4 · MySQL |
| **Database** | `hris-db` |

---

## 1. Cara Menjalankan

```bash
composer install && npm install
php artisan migrate:fresh --seed     # skema + data demo
php artisan storage:link             # untuk foto absensi
composer dev                         # server + queue + vite
```

Buka `http://localhost:8000` → diarahkan ke `/login`.

### Akun demo

Seluruh akun memakai kata sandi **`password`**.

| Email | Role | Akses |
| :--- | :--- | :--- |
| `hr@perusahaan.co.id` | Super Admin / HR | Seluruh modul & konfigurasi |
| `manager@perusahaan.co.id` | Manager / Atasan | Rekap absensi + approval cuti, **dibatasi divisinya** |
| `karyawan@perusahaan.co.id` | Employee | Portal mandiri (absensi, cuti, slip gaji) |
| `mitra@perusahaan.co.id` | Mitra | Portal mandiri, tanpa kuota cuti |

### Isi data demo

59 tenaga kerja aktif — 8 probation · 37 PKWT (3/6/12 bulan) · 14 mitra — dengan
207 slip gaji 6 bulan terakhir, 1.185 record absensi, 42 pengajuan cuti, 86 pelamar,
dan 2 lokasi kantor untuk geofence.

---

## 2. Yang Sudah Selesai

### 2.1 Fase 1 — Core & Entity Rules

#### Core Workforce Database

Sebelas tabel baru mengikuti ERD Masterplan §4, plus satu kolom `role` pada `users`:

| Tabel | Peran |
| :--- | :--- |
| `employment_types` | **Sumber aturan** — `is_leave_eligible`, `is_bpjs_eligible`, `duration_months`, `annual_leave_quota` |
| `employees` | Data induk karyawan & mitra |
| `departments` | Divisi / lokasi |
| `mitra_payroll_schemas` | Skema bayar custom; kolom `components` JSON (Tips §7.1) |
| `attendances` | Absensi + GPS + foto + flag `is_fake_gps` |
| `leave_requests` | Pengajuan cuti & izin |
| `payrolls` | Slip gaji / voucher per periode |
| `job_vacancies`, `applicants` | Data ATS (dipakai dashboard, modulnya Fase 3) |
| `office_locations` | Titik geofence + radius |
| `export_logs` | Audit log ekspor (Masterplan §3) |
| `users.role` | Sumber kebenaran RBAC |

#### RBAC

`app/Http/Middleware/EnsureUserHasRole.php` — dipakai sebagai `role:super_admin,manager`
pada grup route. Manager otomatis discope ke `department_id` miliknya di query absensi
dan cuti, jadi pembatasan terjadi di level query, bukan sekadar sembunyi di UI.

#### Definisi Entitas Kerja

Halaman `/entitas-kerja` mengubah aturan hak cuti, BPJS, durasi, dan kuota. Perubahan
di sini **langsung** mengubah perilaku mesin payroll dan portal cuti — tidak ada aturan
yang di-hardcode di controller.

#### Absensi GPS + Foto + Anti-Fake GPS

`app/Services/AttendanceService.php`:

* **Geofencing** — jarak haversine ke kantor terdekat, ditolak bila di luar radius.
* **Foto selfie** — diambil lewat `getUserMedia`, disimpan ke `storage/app/public/attendance`.
* **Anti-fake GPS**, empat heuristik:
  1. perangkat melaporkan mock location provider;
  2. akurasi mustahil (< 1 meter);
  3. koordinat terlalu bulat (ciri input manual);
  4. perpindahan mustahil (> 200 km/jam dari absen sebelumnya).

Absensi yang ter-flag **tetap tercatat** lalu ditandai untuk verifikasi HR — bukan ditolak
diam-diam, sehingga jejaknya bisa diaudit.

#### Exporting Engine

`app/Services/ExportService.php` — satu jalur untuk seluruh menu:

* Format **xlsx**, **csv**, **pdf**; filter aktif di layar ikut terkirim ke berkas.
* Akses dibatasi RBAC per laporan.
* Setiap unduhan tercatat ke `export_logs`: user, IP, user-agent, waktu, filter, nama berkas, jumlah baris.

Laporan tersedia: Data Induk Tenaga Kerja · Rekap Kontrak Expiring · Laporan Absensi ·
Laporan Keterlambatan · Timesheet Mitra · Rekap Cuti & Izin · Rekap Gaji Bulanan ·
File Transfer Bank (CSV) · Rekap Pajak PPh 21/23.

---

### 2.2 Fase 2 — Payroll Engine & Custom Mitra

#### Mesin gaji PKWT & Probation

`app/Services/PayrollCalculator.php` + `PayrollRunService.php`:

* Potongan pekerja: BPJS Kes 1% + JHT 2% + JP 1% (JP dibatasi cap upah).
* Kontribusi perusahaan: Kes 4% + JHT 3,7% + JKM 0,3% + JKK 0,24% + JP 2%.
* PPh 21 metode **TER PP 58/2023**.
* Lembur ditarik dari menit kerja di atas 8 jam × 1,5 × tarif per jam.
* **Enforcement**: bila `is_bpjs_eligible` bernilai false, seluruh variabel BPJS di-set 0.
* Slip berstatus `paid` tidak ditimpa kecuali dicentang eksplisit.

#### Custom Payroll Schema Builder untuk Mitra

Halaman `/skema-mitra`, lima tipe skema:

| Tipe | Sumber kuantitas |
| :--- | :--- |
| Hourly Rate | **Otomatis** dari total jam kerja timesheet |
| Daily Rate | **Otomatis** dari jumlah hari hadir |
| Fixed Project Fee | Dibayar penuh satu kali per periode |
| Deliverable / Milestone | Persentase pada daftar milestone (JSON) |
| Unit / Output | Diisi manual HR |

Skema pajak mitra: PPh 21 bukan pegawai (berkesinambungan / tidak), PPh 23, atau bebas pajak.

#### Portal Cuti dengan pengecekan otomatis

`app/Services/LeavePolicyService.php` — penolakan terjadi di **server (403)**, bukan
sekadar menyembunyikan tombol:

| Entitas | Cuti tahunan | Izin sakit / tanpa gaji |
| :--- | :--- | :--- |
| Probation | ❌ ditolak 403 | ✅ boleh |
| PKWT | ✅ sesuai kuota | ✅ boleh |
| Mitra | ❌ ditolak 403 | ✅ boleh |

Pengajuan melebihi sisa kuota juga ditolak dengan pesan yang menyebut sisa kuota.

#### Slip gaji & Payment Voucher PDF

Dua template terpisah: `documents/payslip.blade.php` (karyawan) dan
`documents/payment-voucher.blade.php` (mitra, lengkap dengan kolom tanda tangan).
Slip probation secara eksplisit menuliskan "BPJS tidak berlaku untuk entitas ini"
agar karyawan paham sebabnya.

---

### 2.3 Dashboard (Modul 5 — Analytics)

Executive dashboard: hero total tenaga kerja, 4 stat tile, grafik biaya kompensasi
6 bulan (payroll karyawan vs fee mitra), distribusi entitas, peringatan kontrak H-30
(H-14 naik ke severity kritis), absensi hari ini, dan pipeline ATS.

Tema biru muda & putih. Palet grafik divalidasi terhadap gate kontras dan simulasi buta
warna: dua seri biaya (`#184f95` / `#3987e5`) terpisah ΔE 18,9 di simulasi deuteranopia
dan keduanya ≥ 3:1 terhadap permukaan putih. Setiap grafik punya legend, label langsung,
tooltip, dan **toggle Tabel** sehingga nilai tidak pernah hanya bisa dibaca lewat warna.

---

## 3. Hasil Verifikasi

Diuji lewat HTTP kernel Laravel, bukan asumsi:

```
RBAC          Manager→/payroll 403 · Karyawan→/absensi 403 · semua→/dashboard 200
Rule cuti     Probation→tahunan 403 · Probation→sakit 302 · Mitra→tahunan 403
              Mitra→tanpa gaji 302 · PKWT→tahunan 302 (kuota lewat → ditolak)
Payroll run   59 slip, netto Rp 836.532.427
              BPJS Probation 0 · BPJS Mitra 0 · BPJS PKWT Rp 18.716.324
Export        xlsx 12.139 B (signature ZIP valid) · csv 60 baris · pdf 897 KB
              Karyawan→/export/payroll 403 · audit log terisi
PDF           slip & voucher menghasilkan application/pdf
Otorisasi     Karyawan unduh slip milik orang lain → 403
Anti-fake GPS mock provider / akurasi <1 m / koordinat bulat → ter-flag; titik wajar bersih
Geofence      0 m DI DALAM · 400 m di luar · Surabaya di luar
```

`npx tsc --noEmit` bersih · `npm run build` sukses.

---

## 4. Batasan yang Perlu Diketahui

| Batasan | Dampak & rencana |
| :--- | :--- |
| **Anti-fake GPS di browser terbatas** | Web tidak mengekspos flag mock-location Android, jadi `is_mock_location` selalu `false` dari browser. Tiga heuristik lain tetap bekerja. Deteksi penuh butuh aplikasi native/hybrid. |
| **PPh 21 TER disederhanakan** | Baru memakai bracket TER A umum; belum membedakan TER B/C per status PTKP. Perlu tabel lengkap + field PTKP sebelum produksi. |
| **Ekspor masih sinkron** | Aman pada volume saat ini, tapi Tips §7.2 menyarankan background job queue untuk ribuan baris. |
| **Kuantitas mitra unit/milestone manual** | Belum ada UI input kuantitas per periode; sementara dihitung 1× penuh. |
| **Belum ada tes otomatis** | Verifikasi masih lewat smoke test manual. Pest sudah terpasang tapi belum ada test case. |
| **Belum ada notification engine** | Peringatan kontrak H-30/H-14 baru tampil di dashboard, belum dikirim via email/WhatsApp. |
| **`composer audit`** | 30 advisory, seluruhnya dari dependensi Laravel/Symfony bawaan. Jalankan `composer update`. |

---

## 5. Tahap Selanjutnya

### 5.1 Fase 3 — ATS & Analytics (estimasi 3 minggu)

Sesuai roadmap Masterplan §6. Tabel `job_vacancies` dan `applicants` **sudah ada dan
terisi data demo**, jadi Fase 3 tinggal membangun modulnya.

#### A. Portal Karier Publik — ~3 hari

* Route publik (di luar middleware `auth`) menampilkan lowongan berstatus `open`.
* Label kategori pada tiap lowongan: *Full-time PKWT*, *Probation Track*, *Mitra/Freelance*.
* Form lamaran + unggah CV ke storage (`applicants.cv_path` sudah tersedia).
* Perlu ditambah: validasi tipe & ukuran berkas, proteksi spam (honeypot/rate limit).

#### B. Candidate Pipeline (Kanban) — ~5 hari

* Papan kanban: `Applied → Screening → Interview → Offering → Hired`.
* Drag-and-drop antar kolom; butuh library DnD (`@dnd-kit/core` disarankan — ringan, aksesibel).
* Filter per lowongan, per divisi, dan rentang tanggal.
* Detail kandidat: riwayat perpindahan tahap, catatan interview, pratinjau CV.

#### C. One-Click Hired Conversion — ~4 hari

Bagian paling bernilai karena menyambung ATS ke Core HR yang sudah jadi:

1. Saat status diubah ke *Hired*, tampilkan modal pemilihan entitas
   (`Probation 3 Bulan` / `PKWT 3` / `PKWT 6` / `PKWT 12` / `Mitra`).
2. Bila memilih **Mitra**, lanjutkan ke form Skema Pembayaran Custom
   — komponennya bisa dipakai ulang dari `MitraSchemas/Index.tsx`.
3. Buat record `employees` otomatis dari data pelamar (tanpa input ulang),
   isi `converted_employee_id`, hitung `contract_end` dari `duration_months`.
4. Bungkus dalam transaksi DB agar konversi tidak setengah jadi.

#### D. Template Kontrak & Offering Letter — ~3 hari

Sesuai Tips §7.3 — template PDF berbeda untuk Probation, PKWT (3/6/12), dan Mitra.
Infrastruktur PDF sudah ada (dompdf + pola blade), tinggal menambah template.

#### E. Ekspor & Analytics ATS — ~2 hari

* Ekspor Database Pelamar, Laporan Performa Lowongan, Laporan Conversion Rate.
* Semua lewat `ExportService` yang sudah ada — cukup siapkan heading + baris.
* Dashboard ATS: funnel per lowongan, waktu rata-rata per tahap, sumber pelamar.

---

### 5.2 Sisa Modul 1 yang Belum Dikerjakan

Bagian Masterplan §2.1 yang tidak masuk daftar Fase 1:

* **Manajemen Aset & Clearance Sheet** (~4 hari) — inventaris fasilitas yang dipinjamkan
  dan checklist pengembalian saat offboarding. Butuh tabel `company_assets` +
  `asset_assignments`.
* **Exit / Paklaring** (~3 hari) — proses offboarding dan generasi surat keterangan kerja.

---

### 5.3 Modul 5 — Knowledge Center

* **Bulletin Pengumuman** (~2 hari) — pengumuman internal dengan target per divisi/entitas.
* **Storage SOP & Peraturan** (~3 hari) — repositori dokumen dengan kontrol akses per role.

---

### 5.4 Pengerasan Sebelum Produksi

Direkomendasikan dikerjakan sebelum atau paralel dengan Fase 3:

| Prioritas | Pekerjaan |
| :--- | :--- |
| **Tinggi** | Test otomatis Pest untuk rule engine (cuti, BPJS, RBAC) — logika ini paling mahal bila regresi |
| **Tinggi** | Tabel PPh 21 TER lengkap (A/B/C) + field status PTKP pada `employees` |
| **Tinggi** | `composer update` untuk menutup advisory dependensi |
| Sedang | Pindahkan ekspor besar ke queue + notifikasi berkas siap unduh |
| Sedang | Notification engine kontrak H-30/H-14 (email / WhatsApp) |
| Sedang | Lupa kata sandi & ubah kata sandi (belum ada) |
| Sedang | Halaman audit log ekspor untuk Super Admin (datanya sudah tercatat, UI-nya belum ada) |
| Rendah | Halaman manajemen divisi (sekarang hanya lewat seeder) |
| Rendah | Dark mode |

---

## 6. Peta Berkas

```
app/
├── Http/Controllers/
│   ├── Auth/LoginController.php
│   ├── AttendanceController.php          # rekap, self-service, clock in/out, 3 ekspor
│   ├── DashboardController.php           # analytics Modul 5
│   ├── EmployeeController.php            # CRUD + 2 ekspor
│   ├── EmploymentTypeController.php      # definisi entitas
│   ├── LeaveRequestController.php        # portal + approval + ekspor
│   ├── MitraPayrollSchemaController.php  # schema builder
│   └── PayrollController.php             # run, slip, PDF, 3 ekspor
├── Http/Middleware/EnsureUserHasRole.php # RBAC gate
├── Services/
│   ├── AttendanceService.php             # geofence + anti-fake GPS
│   ├── ExportService.php                 # xlsx/csv/pdf + audit log
│   ├── LeavePolicyService.php            # aturan cuti per entitas
│   ├── PayrollCalculator.php             # BPJS + PPh 21 TER + skema mitra
│   └── PayrollRunService.php             # eksekusi periode
└── Exports/TableExport.php

resources/
├── js/
│   ├── Layouts/AppLayout.tsx             # sidebar role-aware
│   ├── Components/                       # ui.tsx, ExportMenu, StatTile, charts/
│   ├── Pages/                            # Auth, Employees, Attendance, Payroll,
│   │                                     # Leaves, MitraSchemas, EmploymentTypes
│   └── lib/format.ts                     # format rupiah & angka id-ID
└── views/
    ├── documents/payslip.blade.php
    ├── documents/payment-voucher.blade.php
    └── exports/table.blade.php           # template PDF generik
```

---

*Diperbarui 1 Agustus 2026 — setelah penyelesaian Fase 1 & Fase 2.*
