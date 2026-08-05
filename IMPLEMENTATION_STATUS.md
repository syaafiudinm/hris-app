# Status Implementasi HRIS & ATS

Dokumen pendamping [`HRIS_ATS_System_Masterplan_v2.md`](HRIS_ATS_System_Masterplan_v2.md).
Berisi apa yang **sudah jadi**, cara menjalankannya, dan **rencana tahap berikutnya**.

| | |
| :--- | :--- |
| **Tanggal** | 6 Agustus 2026 |
| **Progres roadmap** | Fase 1 ✅ · Fase 2 ✅ · Fase 3 ✅ (ATS + Knowledge Center) · Modul 1 lengkap (Exit, Inventaris & Clearance, Absensi 2 opsi) |
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
| `mitra@perusahaan.co.id` | Mitra | Portal mandiri, termasuk kuota cuti |

### Isi data demo

59 tenaga kerja aktif — 8 probation · 37 PKWT (3/6/12 bulan) · 14 mitra — dengan
207 slip gaji 6 bulan terakhir, 1.185 record absensi, 42 pengajuan cuti, 86 pelamar,
dan 2 lokasi kantor untuk geofence.

---

## 2. Yang Sudah Selesai

### 2.1 Fase 1 — Core & Entity Rules

#### Core Workforce Database

Enam belas tabel baru mengikuti ERD Masterplan §4, plus satu kolom `role` pada `users`:

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
| `announcements`, `knowledge_documents` | Knowledge Center + penargetan audiens |
| `employee_exits` | Proses offboarding & penerbitan paklaring |
| `sales_products`, `sales_records` | Katalog produk & unit terjual per mitra per periode |
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

* **Geofencing** — jarak haversine ke kantor terdekat, ditolak bila di luar radius
  (berlaku pada mode kamera langsung; lihat §2.9 untuk mode unggah).
* **Foto selfie** — diambil lewat `getUserMedia`, disimpan ke disk **privat**
  `storage/app/private/attendance` dan dibuka lewat route ber-RBAC.
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

* **Iuran BPJS ditanggung penuh perusahaan** (kebijakan perusahaan pengguna):
  porsi pekerja (Kes 1% + JHT 2% + JP 1%) tidak dipotong dari take home pay,
  melainkan ikut dibayarkan perusahaan bersama porsi perusahaan
  (Kes 4% + JHT 3,7% + JKM 0,3% + JKK 0,24% + JP 2%) — total 14,24% dari upah.
* Ketiga entitas kerja terdaftar BPJS, termasuk Probation dan Mitra.
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
| **Kompensasi Penjualan** | Unit terjual dari menu Penjualan Mitra + hari hadir dari absensi |

Skema pajak mitra: PPh 21 bukan pegawai (berkesinambungan / tidak), PPh 23, atau bebas pajak.

#### Portal Cuti dengan pengecekan otomatis

`app/Services/LeavePolicyService.php` — penolakan terjadi di **server (403)**, bukan
sekadar menyembunyikan tombol:

Sejak kebijakan terbaru ketiga entitas berhak cuti tahunan (lihat §2.6), sehingga
penolakan hanya terjadi bila HR menonaktifkan hak cuti suatu entitas:

| Kondisi | Cuti tahunan | Izin sakit / tanpa gaji |
| :--- | :--- | :--- |
| `is_leave_eligible` = true | ✅ sesuai kuota | ✅ boleh |
| `is_leave_eligible` = false | ❌ ditolak 403 | ✅ tetap boleh |

Pengajuan melebihi sisa kuota juga ditolak dengan pesan yang menyebut sisa kuota.

#### Slip gaji & Payment Voucher PDF

Dua template terpisah: `documents/payslip.blade.php` (karyawan) dan
`documents/payment-voucher.blade.php` (mitra, lengkap dengan kolom tanda tangan).
Setiap komponen dirinci agar penerima dapat mencocokkan sendiri angkanya:

* **Slip karyawan** — tiga bagian: Penerimaan (termasuk jam lembur & tarif per jam),
  Potongan, dan **tabel iuran BPJS per program** (Kesehatan, JHT, JKM, JKK, JP)
  lengkap dengan persentase serta nominal porsi perusahaan dan porsi pekerja yang
  ditalangi. Potongan BPJS Rp 0 diberi keterangan sebabnya.
* **Slip gaji mitra (skema penjualan)** — dasar gaji beserta alasannya (uang makan
  atau bonus tier), baris uang makan yang dicoret bila digantikan bonus, lalu
  perhitungan prorata hari hadir langkah demi langkah.
* **Slip insentif mitra** — satu baris per produk (unit × tarif), dasar dan tarif
  pajaknya. Sengaja **tanpa blok BPJS** agar iuran tidak terhitung dua kali.

Rincian disimpan pada `payrolls.details` saat payroll dijalankan, sehingga slip
mencetak angka yang identik dengan hasil perhitungan — bukan menghitung ulang di
template. Halaman detail payroll di layar menampilkan rincian yang sama.

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

### 2.4 Fase 3 — Recruitment & ATS (Modul 4)

#### Manajemen Lowongan

Halaman `/lowongan` (super admin) untuk membuat, mengubah, dan menghapus lowongan
— inilah sumber data portal karier. Alur statusnya:

| Status | Arti |
| :--- | :--- |
| `draft` | Hanya terlihat internal; aman untuk menyiapkan deskripsi |
| `open` | Tampil di Portal Karier dan menerima lamaran; `published_at` diisi otomatis saat pertama kali dibuka |
| `closed` | Hilang dari portal, pelamar yang sudah masuk tetap ada di pipeline |

Lowongan yang sudah punya pelamar **tidak dapat dihapus** — hanya ditutup, supaya
riwayat rekrutmen tidak ikut hilang.

#### Portal Karier Publik

Route `/karier` di luar middleware `auth`. Menampilkan lowongan `open` dengan label
kategori (*Full-time PKWT* / *Probation Track* / *Mitra-Freelance*), detail lowongan,
dan form lamaran + unggah CV (pdf/doc/docx, maks 5 MB). Anti-spam memakai honeypot
field `website` yang dibalas sukses palsu agar bot tidak belajar.

#### Candidate Pipeline (Kanban)

Halaman `/rekrutmen` mengelompokkan pelamar per tahap
(`Applied → Screening → Interview → Offering → Hired`), dengan kolom Rejected terpisah.
Filter per lowongan dan divisi. Setiap perpindahan tahap dicatat ke `stage_history`
(JSON) lengkap dengan pelaku dan waktunya, plus catatan interview di kolom `notes`.

#### One-Click Hired Conversion

`app/Services/HiredConversionService.php` — satu transaksi DB:

1. Modal memilih entitas (`Probation` / `PKWT 3/6/12` / `Mitra`).
2. Memilih **Mitra** memunculkan form Skema Pembayaran Custom, dan skema itu
   **wajib** diisi — tanpanya mitra akan dilewati diam-diam oleh mesin payroll.
3. Record `employees` dibuat dari data pelamar tanpa input ulang, `contract_end`
   dihitung dari `duration_months` entitas (atau diisi manual untuk mitra).
4. `converted_employee_id` diisi dan tahap dikunci ke *Hired*.

NIK dibangkitkan dari nomor NIK tertinggi yang benar-benar ada — bukan dari `id`
terakhir — sehingga tidak bentrok dengan NIK yang pernah diinput manual oleh HR.

#### Dokumen & Ekspor ATS

Empat template PDF baru: offering letter, kontrak Probation, kontrak PKWT, dan
kontrak Mitra. Ekspor: Database Pelamar (menghormati filter pipeline), Performa
Lowongan, dan Conversion Rate — semuanya lewat `ExportService` yang sudah ada.

#### Keamanan berkas CV

CV pelamar berisi data pribadi, jadi disimpan di **disk privat** (`local`), bukan
`public`. Unduhannya lewat route `/rekrutmen/{applicant}/cv` yang dijaga RBAC
super admin — bukan URL `/storage/...` yang dapat diakses siapa pun.

---

### 2.5 Knowledge Center (Modul 5)

#### Bulletin Pengumuman

Halaman `/knowledge` terbuka untuk semua peran. Pengumuman punya kategori
(Informasi / Kebijakan / Penting), penanda **sematkan**, dan status draft vs terbit —
draft tidak pernah terkirim ke pembaca.

#### Storage SOP & Peraturan

Repositori dokumen dengan jenis SOP, Peraturan Perusahaan, Panduan, dan Formulir,
lengkap dengan versi, ukuran berkas, dan penghitung unduhan. Berkas disimpan di
**disk privat**; unduhan lewat route ber-RBAC, bukan URL `/storage` publik.

#### Penargetan audiens

Trait `App\Models\Concerns\TargetsAudience` dipakai bersama kedua tabel:

| Target | Yang melihat |
| :--- | :--- |
| `all` | Seluruh perusahaan |
| `department` | Anggota satu divisi |
| `employment_category` | Satu kategori entitas (Probation / PKWT / Mitra) |

Penyaringan terjadi di level query lewat scope `visibleTo()`, dan **diperiksa ulang
saat unduh** — bukan sekadar disembunyikan dari daftar.

---

### 2.6 Perubahan Kebijakan Cuti

Sejak 2 Agustus 2026, **ketiga entitas kerja berhak cuti tahunan**:

| Entitas | Kuota | BPJS |
| :--- | :--- | :--- |
| Probation 3 Bulan | 3 hari | tetap dikecualikan |
| PKWT 3 / 6 / 12 Bulan | 3 / 6 / 12 hari | terdaftar |
| Mitra / Freelance | 12 hari | tetap dikecualikan |

Perubahan ini murni **data**, bukan kode — cukup mengubah `is_leave_eligible` dan
`annual_leave_quota` pada `employment_types`, karena rule engine memang membaca dari
sana. Aturan BPJS sengaja tidak ikut diubah dan tetap mengikuti Masterplan §1.2.

---

### 2.7 Exit / Paklaring (Modul 1)

Menutup ujung siklus yang berlawanan dengan ATS: kalau ATS menangani orang **masuk**,
bagian ini menangani orang **keluar**.

Alur dua tahap, `app/Services/ExitService.php`:

| Tahap | Yang terjadi |
| :--- | :--- |
| **Draft** | Jenis, hari kerja terakhir, alasan, dan catatan internal dicatat. Karyawan masih aktif. |
| **Completed** | Status karyawan berubah otomatis (`resigned` / `expired`), nomor paklaring terbit, PDF aktif. |

**Nomor surat** berformat `001/PKL-HR/VIII/2026`, berurutan per tahun, diterbitkan
**sekali** lalu dipakai selamanya — mantan karyawan sering meminta cetak ulang untuk
klaim JHT, melamar kerja, atau pengajuan kredit, dan surat lama harus tetap dapat
diverifikasi. Membuka kembali proses tidak menghapus nomor tersebut.

**Isi paklaring** menyesuaikan penyebab keluar: resign, kontrak berakhir, dan pensiun
memuat keterangan kinerja baik; PHK tidak. Masa kerja dihitung dari `join_date`
sampai hari kerja terakhir.

Catatan cakupan: **Manajemen Aset & Clearance Sheet** sengaja belum dikerjakan, jadi
proses keluar di sini belum menyertakan checklist pengembalian barang.

---

### 2.8 Skema Kompensasi Penjualan Mitra

Skema keenam untuk Mitra, mengikuti kebijakan perusahaan pengguna. Satu periode
menerbitkan **dua slip terpisah** (`payrolls.slip_type`):

**Slip `salary`** — dasar gajinya salah satu, bukan dijumlahkan:

| Kondisi | Dasar gaji bulanan |
| :--- | :--- |
| Tier belum tercapai | Uang makan & transport Rp 1.000.000 |
| Tier tercapai | Bonus **menggantikan** uang makan: 2 unit → 50% UMP · 3 → 75% · 4 → 100% |

Nilai itu diprorata `hari hadir ÷ 26 hari kerja`. Tidak dipotong pajak. Iuran BPJS
perusahaan dibebankan di slip ini saja agar tidak terhitung dua kali.

**Slip `incentive`** — Σ (unit terjual × insentif produk), **tidak diprorata**,
dipotong pajak 50% × 2,5% (efektif 1,25%). Hanya terbit bila ada penjualan.

Tier tertinggi yang syaratnya terpenuhi yang dipakai; UMP acuan Sulsel Rp 3.921.000.

Katalog produk contoh: EX2 Rp 500.000 · EX5 Rp 2.000.000 · Starray Rp 3.000.000,
seluruhnya dapat ditambah/diubah HR lewat menu **Penjualan Mitra**. Nilai uang makan,
hari kerja, UMP acuan, tier bonus, dan tarif pajak diatur **per mitra** pada
`mitra_payroll_schemas.components` lewat Skema Mitra.

Rincian perhitungan disimpan pada `payrolls.details`, sehingga payment voucher
mencetak angka yang sama persis dengan saat payroll dijalankan.

### 2.9 Absensi Dua Opsi (Modul 1)

Logika clock-in kini bercabang lewat kolom baru `attendances.clock_in_method`.

| | `live` — kamera langsung | `upload` — unggah foto |
| :--- | :--- | :--- |
| Sumber foto | `getUserMedia` → data URL base64 | berkas dari perangkat (`image`, maks 5 MB) |
| Geolokasi | wajib | wajib |
| Di luar radius | **ditolak** (`ValidationException`) | diterima, jaraknya dicatat |
| Alasan | tidak diminta | **wajib** diisi |
| Status verifikasi | `auto` — langsung sah | `pending` — menunggu HR |

Alasan opsi kedua ada: kamera browser tidak selalu tersedia (izin ditolak, akses
lewat HTTP biasa yang memblokir `getUserMedia`), dan pekerja lapangan memang
beroperasi di luar radius kantor. Karena foto unggahan tidak dapat dipastikan diambil
saat itu juga, konsekuensinya adalah verifikasi manual — bukan penerimaan buta.

**Kolom baru:** `clock_in_method`, `clock_in_distance`, `clock_in_office`,
`is_outside_radius`, `clock_in_note`, `verification_status`, `verified_by`,
`verified_at`, `verification_note`.

**Keputusan HR** (`AttendanceService::verify`):

* **Disetujui** → status dihitung ulang dari jam clock-in (`present`/`late`).
* **Ditolak** → status diubah menjadi `absent`, `late_minutes` dan `work_minutes`
  dinolkan. Dipilih begitu supaya seluruh rekap, timesheet, dan payroll — yang semuanya
  menyaring `status IN ('present','late')` — otomatis berhenti mengakui hari itu, tanpa
  perlu menambah filter verifikasi di setiap query.

**Foto pindah ke disk privat.** `AttendanceService::PHOTO_DISK = 'local'`, dibuka lewat
route `/absensi/{attendance}/foto` yang mengecek izin per record: pemiliknya sendiri,
super admin, atau manager divisi yang sama. Pola yang sama dipakai CV pelamar dan
dokumen Knowledge Center.

Rekap absensi HR mendapat filter metode & verifikasi, kartu *Menunggu verifikasi*,
kolom **Metode & verifikasi** berisi alasan karyawan + jarak + tautan foto, dan tombol
Setujui/Tolak. Tiga kolom baru ikut pada ekspor absensi.

> **Catatan kebijakan:** absensi `pending` yang belum sempat diverifikasi **masih
> dihitung hadir** oleh payroll. Yang menghapusnya dari perhitungan hanyalah penolakan
> eksplisit. Bila kebijakan perusahaan menghendaki sebaliknya, ubah filter di
> `PayrollRunService` — bukan di controller.

---

### 2.10 Manajemen Peminjaman Inventaris (Modul 1)

Dua tabel baru: `inventory_items` (katalog aset) dan `inventory_loans` (siklus pinjam).

Aset menyimpan `quantity` sebagai total unit — barang serial cukup diisi 1, barang
generik diisi jumlah sebenarnya. Ketersediaan **tidak disimpan**, melainkan dihitung
`quantity − Σ kuantitas pinjaman berstatus approved/borrowed`, sehingga tidak ada
kolom yang bisa melenceng dari kenyataan.

**Mesin status** ada seluruhnya di `InventoryService::TRANSITIONS`:

```
requested → approved | rejected
approved  → borrowed | rejected
borrowed  → returned | lost
returned / rejected / lost → (final)
```

Transisi di luar peta ini ditolak dengan `ValidationException`, jadi UI tidak bisa
melompati alur. Tombol yang tampil di layar dibangkitkan dari peta yang sama —
`presentLoan()` mengirim daftar transisi yang sah sebagai `actions`.

**Efek samping tiap transisi:**

* `approve` — mengunci stok di dalam `DB::transaction` + `lockForUpdate`, jadi dua
  persetujuan bersamaan tidak dapat mem-booking unit yang sama dua kali. Bila stok
  kurang, persetujuan gagal dengan pesan sisa unit.
* `returnItem` — melepas stok; kondisi yang lebih buruk menurunkan kondisi master
  aset, dan `damaged` memindahkannya ke status `maintenance`.
* `markLost` — mengurangi `quantity` permanen; habis berarti aset `retired`.

**Portal mandiri** `/inventaris-saya`: pegawai mengajukan, memantau, dan membatalkan
pengajuan miliknya sendiri (dicek `employee_id`, bukan sekadar disembunyikan di UI).
Pengajuan **belum** menahan stok — penguncian baru terjadi saat HR menyetujui.

**Konsol HR** `/inventaris` (super admin): CRUD katalog, antrean pengajuan, filter
*Lewat jatuh tempo*, pencatatan pinjaman atas nama pegawai (langsung disetujui), dan
ekspor riwayat.

**Pagar pengaman:** aset dengan pinjaman berjalan tidak dapat dihapus, dan `quantity`
tidak dapat diturunkan di bawah jumlah yang sedang dipinjam.

**Integrasi clearance.** `ExitController::updateStatus` menolak penuntasan proses keluar
selama karyawan masih punya pinjaman berstatus requested/approved/borrowed, dan
menyebutkan nama asetnya. Kartu draft di halaman Proses Keluar menampilkan peringatan
sejak awal. Inilah bagian "Clearance Sheet" dari Masterplan §2.1 yang sebelumnya
tertunda.

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

### Test otomatis (Pest)

```
Tests: 85 passed (282 assertions)

JobVacancyTest         buat lowongan · alur draft/open/closed · proteksi hapus · RBAC
EmployeeExitTest       alur draft->completed · nomor surat stabil · masa kerja · RBAC
SalesCompensationTest  bonus menggantikan uang makan · dua slip terpisah · pajak insentif · BPJS
KnowledgeCenterTest    penargetan audiens · disk privat · RBAC · kebijakan cuti baru
AttendanceModeTest     mode live diblokir di luar radius · mode upload diterima & pending
                       · berkas + alasan wajib · setujui mengembalikan kehadiran
                       · tolak mengubah hari jadi absent · foto privat per-record
InventoryLoanTest      stok terkunci saat disetujui · stok kurang ditolak · transisi
                       melompat ditolak · rusak berat menurunkan kondisi aset · hilang
                       mengurangi unit · aset terpakai tak bisa dihapus · clearance exit
RecruitmentTest        portal karier · pipeline · konversi hired
RecruitmentGuardTest   regresi ATS:
  · form konversi tersedia di papan pipeline DAN halaman detail (opsi identik)
  · konversi mitra tanpa skema ditolak, bukan error 500
  · konversi mitra dengan skema menghasilkan skema pembayaran + contract_end
  · pelamar rejected tidak dapat dikonversi
  · NIK hasil konversi tidak bentrok dengan NIK input manual
  · CV di disk privat; hanya super admin dapat mengunduh
  · tamu diarahkan ke login saat mengakses CV
  · ekspor pelamar menghormati filter lowongan
```

Query per halaman setelah perbaikan N+1: `/rekrutmen` 27 → 18, `/karier` 10 → 6.

---

## 4. Batasan yang Perlu Diketahui

| Batasan | Dampak & rencana |
| :--- | :--- |
| **Anti-fake GPS di browser terbatas** | Web tidak mengekspos flag mock-location Android, jadi `is_mock_location` selalu `false` dari browser. Tiga heuristik lain tetap bekerja. Deteksi penuh butuh aplikasi native/hybrid. |
| **PPh 21 TER disederhanakan** | Baru memakai bracket TER A umum; belum membedakan TER B/C per status PTKP. Perlu tabel lengkap + field PTKP sebelum produksi. |
| **Ekspor masih sinkron** | Aman pada volume saat ini, tapi Tips §7.2 menyarankan background job queue untuk ribuan baris. |
| **Kuantitas mitra unit/milestone manual** | Belum ada UI input kuantitas per periode; sementara dihitung 1× penuh. |
| **Cakupan tes masih parsial** | 85 test menutup ATS, Knowledge Center, Exit/Paklaring, skema penjualan, absensi dua opsi, dan peminjaman inventaris. Rule engine cuti/BPJS/RBAC masih diverifikasi lewat smoke test manual, belum jadi test Pest. |
| **Kanban belum drag-and-drop** | Perpindahan tahap lewat tombol/select, bukan seret-lepas. Fungsional, tapi belum senyaman papan kanban penuh. |
| **Belum ada notification engine** | Peringatan kontrak H-30/H-14 baru tampil di dashboard, belum dikirim via email/WhatsApp. |
| **Lamaran publik belum di-rate-limit** | Honeypot sudah ada, tapi belum ada throttle per IP pada `/karier/{id}/apply`. |
| **`composer audit`** | 30 advisory, seluruhnya dari dependensi Laravel/Symfony bawaan. Jalankan `composer update`. |

---

## 5. Tahap Selanjutnya

### 5.1 Penyempurnaan Modul ATS

Inti Fase 3 sudah jalan; sisa yang membuatnya matang:

* **Kanban drag-and-drop** (~2 hari) — `@dnd-kit/core` disarankan (ringan, aksesibel).
* **Rate limit lamaran publik** (~1 jam) — `throttle:5,60` pada route apply.
* **Dashboard ATS lanjutan** (~2 hari) — waktu rata-rata per tahap dan sumber pelamar;
  funnel dasarnya sudah ada di dashboard utama.
* **Notifikasi pelamar** (~2 hari) — email otomatis saat tahap berubah.

---

### 5.2 Sisa Modul 1 yang Belum Dikerjakan

Manajemen Aset & Clearance Sheet **sudah dikerjakan** — lihat §2.10. Yang tersisa
sekadar penyempurnaan, bukan modul baru:

* **Berita acara serah terima PDF** (~1 hari) — dokumen tanda tangan saat barang
  diserahkan dan dikembalikan; polanya sama dengan paklaring.
* **Pengingat jatuh tempo otomatis** (~1 hari) — sekarang keterlambatan hanya terlihat
  bila HR membuka halaman Inventaris; idealnya masuk notification engine bersama
  peringatan kontrak H-30/H-14.
* **Foto kondisi barang** (~1 hari) — bukti visual saat serah terima dan pengembalian,
  memakai disk privat yang sama dengan foto absensi.
* **Riwayat pemakaian per aset** (~½ hari) — halaman detail aset berisi seluruh
  peminjam sebelumnya dan perubahan kondisinya.

---

### 5.4 Pengerasan Sebelum Produksi

| Prioritas | Pekerjaan |
| :--- | :--- |
| **Tinggi** | Test Pest untuk PPh 21 TER dan perhitungan lembur — bagian payroll yang belum tertutup |
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
│   ├── AttendanceController.php          # rekap, clock in 2 opsi, verifikasi, foto privat, 3 ekspor
│   ├── CareerController.php              # portal karier publik + form lamaran
│   ├── ExitController.php                # offboarding + paklaring + cek clearance
│   ├── InventoryController.php           # katalog aset, siklus pinjam, portal mandiri
│   ├── SalesController.php               # katalog produk + input unit terjual
│   ├── JobVacancyController.php          # CRUD lowongan + alur publikasi
│   ├── KnowledgeController.php           # pengumuman + dokumen + unduh privat
│   ├── RecruitmentController.php         # pipeline, konversi, PDF, unduh CV, 3 ekspor
│   ├── DashboardController.php           # analytics Modul 5
│   ├── EmployeeController.php            # CRUD + 2 ekspor
│   ├── EmploymentTypeController.php      # definisi entitas
│   ├── LeaveRequestController.php        # portal + approval + ekspor
│   ├── MitraPayrollSchemaController.php  # schema builder
│   └── PayrollController.php             # run, slip, PDF, 3 ekspor
├── Http/Middleware/EnsureUserHasRole.php # RBAC gate
├── Services/
│   ├── AttendanceService.php             # geofence, anti-fake GPS, 2 opsi foto, verifikasi
│   ├── ExportService.php                 # xlsx/csv/pdf + audit log
│   ├── ExitService.php                   # tuntaskan exit + nomor paklaring
│   ├── HiredConversionService.php        # pelamar -> karyawan, satu transaksi
│   ├── InventoryService.php              # mesin status pinjaman + hitung stok
│   ├── LeavePolicyService.php            # aturan cuti per entitas
│   ├── PayrollCalculator.php             # BPJS + PPh 21 TER + skema mitra
│   └── PayrollRunService.php             # eksekusi periode
└── Exports/TableExport.php

resources/
├── js/
│   ├── Layouts/AppLayout.tsx             # sidebar role-aware
│   ├── Components/                       # ui.tsx, ExportMenu, StatTile,
│   │                                     # ConversionModal (dipakai 2 halaman), charts/
│   ├── Pages/                            # Auth, Employees, Attendance, Payroll,
│   │                                     # Leaves, MitraSchemas, EmploymentTypes,
│   │                                     # Career (publik), Recruitment, Vacancies,
│   │                                     # Knowledge (baca + kelola), Exits, Sales
│   └── lib/format.ts                     # format rupiah & angka id-ID
└── views/
    ├── documents/payslip.blade.php
    ├── documents/payment-voucher.blade.php
    ├── documents/offering-letter.blade.php
    ├── documents/contract-{probation,pkwt,mitra}.blade.php
    ├── documents/paklaring.blade.php
    └── exports/table.blade.php           # template PDF generik

tests/Feature/
├── EmployeeExitTest.php                  # offboarding & paklaring
├── JobVacancyTest.php                    # manajemen lowongan
├── SalesCompensationTest.php             # skema penjualan mitra & BPJS
├── KnowledgeCenterTest.php               # audiens, disk privat, kebijakan cuti
├── RecruitmentTest.php                   # alur utama ATS
└── RecruitmentGuardTest.php              # regresi bug ATS
```

---

*Diperbarui 3 Agustus 2026 — Modul 1–5 aktif plus skema kompensasi penjualan mitra; menyisakan Manajemen Aset & Clearance Sheet.*
