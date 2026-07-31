# Master Plan & Arsitektur Sistem HRIS & ATS Terintegrasi (v2.0)

Dokumen Spesifikasi Sistem, Aturan Entitas Tenaga Kerja, Skema Gaji Custom Mitra, Fitur Ekspor Data, Skema Database, dan Panduan Implementasi.

---

## 1. Pendahuluan & Ringkasan Eksekutif

### 1.1 Latar Belakang
Sistem **HRIS (Human Resource Information System) & ATS (Applicant Tracking System)** terintegrasi ini dirancang untuk mengelola seluruh siklus hidup tenaga kerja (*workforce lifecycle*) dari berbagai kategori entitas kerja (Karyawan Tetap/PKWT/Probation dan Mitra/Freelancer). Sistem ini memusatkan proses rekrutmen, absensi, kalkulasi kompensasi, hingga *offboarding* dalam satu platform terpadu.

### 1.2 Penyesuaian Kategori Entitas Tenaga Kerja
1. **Karyawan - Masa Percobaan (Probation 3 Bulan):**
   * **Exclude Cuti:** Tidak mendapatkan kuota cuti tahunan selama masa percobaan.
   * **Exclude BPJS:** Belum didaftarkan kepesertaan BPJS Kesehatan maupun BPJS Ketenagakerjaan.
2. **Karyawan - PKWT (3, 6, dan 12 Bulan):**
   * **Include Semua Hak:** Mendapatkan hak cuti tahunan/proporsional, kepesertaan BPJS Kes & BPJS TK, serta pemotongan PPh 21 metode TER.
3. **Mitra / Freelancer / Contractor:**
   * **Custom Payroll Schema:** Skema pembayaran/kompensasi yang sepenuhnya dapat disesuaikan (*customizable*) berbasis *hourly rate*, *daily rate*, *fixed project*, *milestone/deliverable*, maupun *unit output*.

### 1.3 Standar Fitur Ekspor Data (Global Exporting)
Seluruh modul dan sub-menu dalam sistem dilengkapi dengan kemampuan **Ekspor Data** (*Export Report*) dalam format **Excel (.xlsx)**, **CSV**, dan **PDF** yang dapat difilter berdasarkan rentang tanggal, divisi, lokasi, dan status entitas kerja.

---

## 2. Arsitektur & Modul Utama

```
+---------------------------------------------------------------------------------+
|                                 WEB HR SYSTEM                                   |
+-------------------+--------------------+-------------------+--------------------+
| 1. Workforce Data | 2. Attendance &    | 3. Payroll &      | 4. Recruitment &   |
|    & Ops          |    Time Off        |    Compensation   |    ATS             |
+-------------------+--------------------+-------------------+--------------------+
| • Profil & Berkas | • Absen Foto+GPS   | • Gaji Karyawan   | • Portal Karier    |
| • Akses (RBAC)    | • Anti-Fake GPS    |   (PKWT vs Prob)  | • Pipeline Seleksi |
| • PKWT/Prob Alert | • Rule Cuti/Izin   | • Skema Custom    | • Auto-Convert to  |
| • Aset Perusahaan |   berdasar Entitas |   Gaji Mitra      |   Prob/PKWT/Mitra  |
| • Exit/Paklaring  | • Rekap & Timesheet| • Payment Voucher | • Forms & CV Vault |
+-------------------+--------------------+-------------------+--------------------+
|                     5. HR Analytics & Knowledge Center                          |
|    • Dashboard Analytics  • Bulletin Pengumuman  • Storage SOP & Peraturan      |
+---------------------------------------------------------------------------------+
|               [GLOBAL FEATURE] EXPORT DATA (EXCEL / CSV / PDF)                  |
+---------------------------------------------------------------------------------+
```

### 2.1 Modul 1: Workforce Data & Operations (Core HR)
Pondasi pengelolaan data seluruh identitas kerja dengan pemisahan aturan entitas yang jelas.
* **Klasifikasi Entitas Kerja:**
  * `Probation (3 Bulan)`: Penandaan otomatis sistem untuk pembatasan fitur cuti dan kalkulasi BPJS.
  * `PKWT (3, 6, 12 Bulan)`: Penandaan durasi kontrak dengan perhitungan hak kompensasi penuh.
  * `Mitra`: Profil khusus kemitraan/freelance tanpa ikatan hubungan kerja tetap.
* **Peringatan Masa Kontrak & Evaluasi (Notification Engine):**
  * Notifikasi otomatis H-30 dan H-14 sebelum masa *Probation* (3 bulan), kontrak *PKWT* (3/6/12 bulan), atau Kontrak *Mitra* berakhir.
* **Role-Based Access Control (RBAC):**
  * `Super Admin / HR`: Akses penuh konfigurasi sistem, payroll, dan ekspor data.
  * `Manager / Atasan`: Akses verifikasi/approval absensi, klaim, dan evaluasi tim.
  * `Employee / Mitra`: Portal mandiri (*Self-Service*) sesuai limitasi hak entitas masing-masing.
* **Manajemen Aset & Clearance Sheet:** Inventarisasi fasilitas kantor yang dipinjamkan dan *checklist* pengembalian barang saat *offboarding*.
* **Fitur Ekspor Menu Core HR:** Ekspor Data Induk Karyawan & Mitra, Rekap Kontrak Expiring, List Aset Perusahaan.

---

### 2.2 Modul 2: Time, Attendance & Leave Management
Sistem pencatatan kehadiran yang fleksibel dan beradaptasi dengan status entitas kerja.
* **Absensi Presisi Digital:** *Clock-in/out* berbasis GPS (*Geofencing*), Verifikasi Foto Wajah (*Selfie*), dan sistem Anti-Fake GPS (*Mock Location Detection*).
* **Aturan Cuti & Izin Berdasarkan Entitas (Leave Policy Rules):**
  * **Probation:** Pengajuan cuti tahunan otomatis **ditolak/di-block** oleh sistem. Hanya dapat mengajukan izin khusus (sakit/ijin potong gaji) jika diizinkan kebijakan.
  * **PKWT (3, 6, 12 Bulan):** Kuota cuti tahunan/proporsional aktif dan dapat diajukan sesuai *approval workflow*.
  * **Mitra:** Pengelolaan *timesheet* kerja / *working hours log* tanpa kalkulasi kuota cuti tahunan.
* **Fitur Ekspor Menu Kehadiran:** Ekspor Laporan Absensi Harian/Bulanan, Laporan Keterlambatan, Timesheet Jam Kerja Mitra, Rekap Cuti & Izin.

---

### 2.3 Modul 3: Payroll & Compensation Engine (Karyawan & Mitra)
Mesin penggajian terpisah antara regulasi karyawan tetap/kontrak dan keluwesan pembayaran mitra.

#### A. Skema Gaji Karyawan (PKWT & Probation)
* **Karyawan PKWT (3, 6, 12 Bulan):**
  * **Komponen Penerimaan:** Gaji Pokok + Tunjangan Tetap/Tidak Tetap + Upah Lembur.
  * **Potongan Wajib:** PPh 21 (TER PP 58/2023) + BPJS Kesehatan (1%) + BPJS Ketenagakerjaan (JHT 2% + JP 1%).
  * **Kontribusi Perusahaan:** BPJS Kesehatan (4%) + BPJS TK (JHT 3.7%, JKM 0.3%, JKK 0.24%-1.74%, JP 2%).
* **Karyawan Probation (3 Bulan):**
  * **Komponen Penerimaan:** Gaji Pokok + Tunjangan (Jika ada) + Upah Lembur.
  * **Potongan Wajib:** PPh 21 TER (Jika Penghasilan Melewati PTKP).
  * **Aturan Khusus:** Sistem secara otomatis **mengabaikan/meniadakan** pemotongan dan kontribusi BPJS Kesehatan & BPJS Ketenagakerjaan.

#### B. Skema Gaji Mitra yang Dapat Di-Custom (Custom Partner Payroll Schema)
Sistem menyediakan *Builder Skema Pembayaran Mitra* yang dapat dikonfigurasi secara fleksibel per individu atau per proyek:
1. **Opsi Komponen Dasar Kompensasi Mitra:**
   * **Fixed Project Fee:** Pembayaran bernilai tetap per proyek/spesifikasi kerja.
   * **Hourly Rate / Jam Kerja:** Pembayaran berbasis total jam kerja pada *timesheet* (Jam Kerja x Tarif/Jam).
   * **Daily Rate / Per Hari:** Pembayaran berbasis jumlah hari hadir (Hari Kerja x Tarif/Hari).
   * **Deliverable / Milestone-Based:** Pembayaran dicairkan bertahap berdasarkan persentase penyelesaian tugas (contoh: Phase 1: 30%, Phase 2: 70%).
   * **Unit / Output-Based:** Pembayaran berdasarkan kuantitas hasil kerja (Jumlah Unit Selesai x Tarif/Unit).
2. **Komponen Tambahan & Potongan Mitra (Custom Adjustments):**
   * Tunjangan Operasional/Transport Mitra (Optional).
   * Bonus Performa / Penalty keterlambatan *deadline*.
   * Skema Pajak Mitra: PPh 21 Bukan Pegawai (Berkesinambungan/Tidak Berkesinambungan) atau PPh 23 / Bebas Pajak (sesuai regulasi & NPWP Mitra).
3. **Dokumen Pembayaran:** Generasi otomatis *Payment Voucher* / Slip Pembayaran Mitra dalam bentuk PDF.

* **Fitur Ekspor Menu Payroll:** Ekspor Rekap Gaji Bulanan (Excel/CSV), File Transfer Bank Massal (BCA/Mandiri/BNI/BRI CSV Format), Rekap Pajak PPh 21/23, Slip Gaji PDF Batch.

---

### 2.4 Modul 4: Recruitment & Applicant Tracking System (ATS)
Solusi akuisisi bakat dengan fleksibilitas penetapan entitas sejak tahap penawaran (*offering*).
* **Portal Karier Publik:** Menampilkan lowongan kerja dengan label kategori (*Full-time PKWT*, *Probation Track*, atau *Mitra/Freelance*).
* **Candidate Pipeline (Kanban Board):** Tahapan seleksi visual (*Applied* -> *Screening* -> *Interview* -> *Offering* -> *Hired*).
* **One-Click Hired Conversion (Penetapan Entitas):**
  Saat mengubah status kandidat menjadi *Hired*, sistem menampilkan *pop-up modal* untuk menentukan:
  1. Jenis Entitas (`Probation 3 Bulan`, `PKWT 3 Bulan`, `PKWT 6 Bulan`, `PKWT 12 Bulan`, atau `Mitra`).
  2. Apabila memilih `Mitra`, sistem langsung mengarahkan ke form pemilihan *Custom Payroll Schema*.
  3. Mengonversi data pelamar menjadi data akun karyawan/mitra secara otomatis tanpa *input* ulang.
* **Fitur Ekspor Menu ATS:** Ekspor Database Pelamar, Laporan Performa Lowongan, Laporan Conversion Rate Rekrutmen.

---

### 2.5 Modul 5: HR Analytics & Knowledge Center
Pusat informasi eksekutif untuk analisis rasio SDM dan pengeluaran kompensasi.
* **Executive Dashboard Analytics:**
  * Grafik Distribusi Tenaga Kerja (Rasio Karyawan PKWT, Probation, dan Mitra).
  * Biaya Kompensasi (Memisahkan *Total Payroll Karyawan* vs *Total Payment Fee Mitra*).
  * Statistik Kehadiran & Produktivitas Mitra.
  * Peringatan Kontrak Kadaluarsa (Kontrak PKWT & Mitra Expiring H-30).
* **Fitur Ekspor Menu Analytics:** Ekspor Executive Summary Dashboard (PDF), Rekap Data Analitik SDM (Excel).

---

## 3. Spesifikasi Fitur Ekspor Data (Menu Exporting Engine)

Untuk memastikan fleksibilitas pelaporan, **setiap menu/halaman** dalam aplikasi HRIS dilengkapi tombol **"Export Data"** dengan spesifikasi teknis berikut:

| Parameter | Spesifikasi & Ketentuan |
| :--- | :--- |
| **Pilihan Format** | **Excel (.xlsx)** (Laporan detail & rumus), **CSV** (Integrasi perbankan/sistem lain), **PDF** (Dokumen cetak/resmi). |
| **Filter Ekspor** | • **Rentang Tanggal:** Harian, Mingguan, Bulanan, Custom Date Range.<br>• **Kategori Entitas:** All, Probation, PKWT (3/6/12 Bln), Mitra.<br>• **Divisi / Departemen:** All atau Pilihan Divisi Spesifik.<br>• **Status:** Aktif, Non-Aktif, Expired. |
| **Keamanan Ekspor** | • Hak akses tombol ekspor dikontrol via RBAC (hanya Role tertentu yang bisa ekspor).<br>• *Audit Log*: Mencatat User ID, IP Address, Waktu, dan Nama File yang diekspor. |

---

## 4. Skema Basis Data (Entity Relationship Diagram - Revised)

```
+-------------------+       +-------------------+       +-----------------------+
|    employment_    |       |     employees     |------>|  mitra_payroll_schemas|
|      types        |<------|                   | 1   1 | (Custom Schema Mitra) |
+-------------------+ 1   N +-------------------+       +-----------------------+
| id (PK)           |       | id (PK)           |       | id (PK)               |
| name (Probation/  |       | user_id (FK)      |       | employee_id (FK)      |
|  PKWT/Mitra)      |       | employment_type_id|       | schema_type (Hourly/  |
| duration_months   |       | nik, full_name    |       |  Daily/Project/Unit)  |
| is_leave_eligible |       | contract_start    |       | rate_per_unit         |
| is_bpjs_eligible  |       | contract_end      |       | custom_tax_percentage |
+-------------------+       +-------------------+       +-----------------------+
                                    |
            +-----------------------+-----------------------+
            | N                     | N                     | N
+-------------------+    +-------------------+    +-------------------+
|    attendances    |    |   leave_requests  |    |     payrolls      |
+-------------------+    +-------------------+    +-------------------+
| id (PK)           |    | id (PK)           |    | id (PK)           |
| employee_id (FK)  |    | employee_id (FK)  |    | employee_id (FK)  |
| clock_in, out     |    | leave_type        |    | basic_amount      |
| lat, long, photo  |    | start_date, end   |    | bpjs_deduction    |
| is_fake_gps       |    | status (approved) |    | pph_deduction     |
+-------------------+    +-------------------+    | net_payout        |
                                                  +-------------------+
```

---

## 5. Panduan Teknis & Arsitektur Sistem

### 5.1 Tech Stack yang Direkomendasikan
* **Frontend:** React.js / Next.js atau Vue.js / Nuxt.js + Tailwind CSS.
* **Backend:** Node.js (NestJS / Express), Go (Golang), atau Laravel (PHP 8.2+).
* **Database:** PostgreSQL (Mendukung transaksi finansial kompleks & relasi data).
* **Penyimpanan Berkas (Storage):** AWS S3, Google Cloud Storage, atau MinIO.
* **Library Ekspor:** `exceljs` / `xlsx` (Excel), `pdfmake` / `puppeteer` (PDF).

### 5.2 Rule Engine Enforcement di Server-Side
Sistem backend **wajib** memvalidasi logika bisnis berikut:
1. **Validasi Cuti:** Ketika API pengajuan cuti dipanggil, sistem memeriksa `employment_types.is_leave_eligible`. Jika `false` (Probation/Mitra), sistem mengembalikan *response error 403 Forbidden*.
2. **Validasi BPJS:** Mesin payroll memverifikasi `employment_types.is_bpjs_eligible`. Jika `false`, variabel pemotongan BPJS di-set `0`.
3. **Kalkulasi Mitra:** Mesin payroll mengecek apakah tipe entitas adalah `Mitra`. Jika ya, sistem membaca tabel `mitra_payroll_schemas` untuk menjalankan kalkulasi kustom.

---

## 6. Roadmap Pengembangan (MVP Approach)

```
[ Fase 1: Core & Entity Rules (Waktu: 4 Minggu) ]
├── Core Workforce Database & RBAC
├── Definisi Entitas (Probation, PKWT 3/6/12 Bln, Mitra)
├── Absensi GPS + Foto + Anti-Fake GPS
└── Fitur Ekspor Data di Menu Core & Absensi

[ Fase 2: Payroll Engine & Custom Mitra (Waktu: 4 Minggu) ]
├── Mesin Gaji PKWT & Probation (Rule BPJS & PPh 21 TER)
├── Custom Payroll Schema Builder untuk Mitra
├── Portal Cuti/Izin (Pengecekan Otomatis Hak Cuti)
└── Generator Slip Gaji & Payment Voucher PDF + Fitur Ekspor

[ Fase 3: ATS & Analytics (Waktu: 3 Minggu) ]
├── Portal Karier Publik & Kanban ATS Pipeline
├── One-Click Hired Conversion ke Probation/PKWT/Mitra
├── Dashboard Executive Analytics
└── Fitur Ekspor Data ATS & Analytics
```

---

## 7. Tips Praktis Implementasi

1. **Fleksibilitas Custom Schema Mitra:**
   Gunakan struktur kolom JSON (`jsonb` di PostgreSQL) pada tabel `mitra_payroll_schemas` untuk menyimpan variabel komponen custom (seperti variabel bonus, potongan khusus, atau daftar milestone) agar mudah disesuaikan tanpa perlu mengubah struktur tabel database di kemudian hari.

2. **Optimasi Fitur Ekspor Data Berukuran Besar:**
   Untuk ekspor data dalam jumlah besar (misal: ribuan rekap absensi), jalankan proses ekspor secara *asynchronous* menggunakan *background job queue* (seperti BullMQ/Redis). Berikan notifikasi jika file Excel/PDF sudah selesai digenerate dan siap diunduh.

3. **Integritas Penentuan Entitas Saat Onboarding ATS:**
   Sediakan *template contract draft* yang berbeda untuk Probation, PKWT (3/6/12 bulan), dan Mitra pada modul ATS, sehingga HR dapat langsung mencetak atau mengirimkan surat penawaran (*offering letter*) yang sesuai begitu status pelamar diubah menjadi *Hired*.

---
*Dokumen Masterplan HRIS & ATS v2.0 ini siap dijadikan acuan utama oleh Tim Pengembang (Developers) dan Product Owner.*
