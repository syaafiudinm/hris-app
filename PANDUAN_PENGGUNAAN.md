# Panduan Penggunaan HRIS & ATS

Panduan langkah demi langkah untuk pengguna sistem. Untuk detail teknis dan status
pengembangan, lihat [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md).

---

## Daftar Isi

1. [Sekilas sistem](#1-sekilas-sistem)
2. [Masuk & keluar](#2-masuk--keluar)
3. [Portal mandiri — untuk semua karyawan & mitra](#3-portal-mandiri--untuk-semua-karyawan--mitra)
4. [Panduan Manager / Atasan](#4-panduan-manager--atasan)
5. [Panduan HR / Super Admin](#5-panduan-hr--super-admin)
6. [Portal Karier — untuk pelamar](#6-portal-karier--untuk-pelamar)
7. [Ekspor data](#7-ekspor-data)
8. [Pertanyaan yang sering muncul](#8-pertanyaan-yang-sering-muncul)

---

## 1. Sekilas sistem

Sistem ini mengelola tiga jenis tenaga kerja, dan **hak tiap jenis berbeda**:

| Entitas | Cuti tahunan | BPJS | Cara dibayar |
| :--- | :--- | :--- | :--- |
| **Probation** (3 bulan) | 3 hari | Ditanggung perusahaan | Gaji pokok + tunjangan + lembur |
| **PKWT** (3/6/12 bulan) | 3 / 6 / 12 hari | Ditanggung perusahaan | Gaji pokok + tunjangan + lembur |
| **Mitra / Freelance** | 12 hari | Ditanggung perusahaan | Skema custom, termasuk **kompensasi penjualan** |

Dua kebijakan yang berlaku untuk **ketiga entitas**:

* **Cuti tahunan** — semuanya punya kuota, proporsional terhadap durasi kontrak.
* **BPJS** — semuanya didaftarkan, dan **iurannya ditanggung penuh perusahaan**.
  Porsi pekerja (1% Kesehatan + 2% JHT + 1% JP) tidak dipotong dari penerimaan;
  perusahaan yang menalanginya. Slip gaji menampilkan potongan BPJS Rp 0.

Perbedaan ini **ditegakkan otomatis oleh sistem**, bukan bergantung pada ketelitian
petugas.

> Kuota dan hak di atas adalah konfigurasi, bukan aturan mati. HR dapat mengubahnya
> kapan saja lewat menu **Entitas Kerja** — lihat bagian 5.2.

### Tiga peran pengguna

| Peran | Bisa apa |
| :--- | :--- |
| **Employee / Mitra** | Portal mandiri saja: absensi, cuti, slip gaji miliknya sendiri |
| **Manager / Atasan** | Portal mandiri + rekap absensi & approval cuti **untuk divisinya saja** |
| **Super Admin / HR** | Semua modul: data karyawan, payroll, lowongan, rekrutmen, konfigurasi |

---

## 2. Masuk & keluar

1. Buka alamat sistem — Anda akan diarahkan ke halaman **Masuk**.
2. Isi email dan kata sandi, lalu klik **Masuk**.
3. Centang **Ingat saya di perangkat ini** bila memakai perangkat pribadi.

Untuk keluar, klik **Keluar** di kotak identitas pada bagian bawah menu samping.

> **Akun demo** (kata sandi semua: `password`)
> `hr@perusahaan.co.id` · `manager@perusahaan.co.id` ·
> `karyawan@perusahaan.co.id` · `mitra@perusahaan.co.id`

Menu yang tampil di samping menyesuaikan peran Anda — jika suatu menu tidak terlihat,
berarti peran Anda memang tidak memiliki akses ke sana.

---

## 3. Portal mandiri — untuk semua karyawan & mitra

### 3.1 Absensi Saya

Menu **Portal Saya → Absensi Saya**.

Clock in menyediakan **dua opsi**. Pilih salah satu kartu di bagian atas kotak
*Clock in*; kedua opsi sama-sama merekam titik GPS Anda.

#### Opsi 1 — Kamera langsung (baku)

Untuk absen normal dari kantor.

1. Klik **Ambil lokasi**. Izinkan akses lokasi saat browser meminta.
   Sistem menampilkan jarak Anda ke kantor terdekat:
   * Hijau bertanda centang = di dalam radius, boleh lanjut.
   * Merah = di luar radius; clock in **ditolak**.
2. Klik **Nyalakan kamera**, lalu **Ambil foto**. Bila hasilnya kurang baik, klik
   **Ulangi foto**.
3. Klik **Kirim clock in**.

Absensi ini **langsung sah** — tidak perlu persetujuan siapa pun.

#### Opsi 2 — Unggah foto

Untuk dua keadaan: Anda bekerja di lapangan (di luar radius kantor), atau kamera
browser tidak dapat dipakai (perangkat menolak izin, akses lewat HTTP biasa,
kamera dipakai aplikasi lain).

1. Klik **Ambil lokasi** seperti biasa. Bedanya, berada di luar radius **tidak
   memblokir** — jaraknya hanya dicatat.
2. Klik **Pilih foto** dan pilih berkas dari perangkat (JPG/PNG, maksimal 5 MB).
3. Isi **alasan** — wajib. Contoh: "Kunjungan klien di Gowa" atau "Kamera ponsel
   tidak bisa dibuka". Alasan ini yang dibaca HR saat memverifikasi.
4. Klik **Kirim untuk verifikasi**.

Absensi tercatat hari itu juga, tetapi berstatus **Menunggu verifikasi HR**.
Hasil keputusan muncul di kartu status Anda:

| Keputusan HR | Akibatnya |
| --- | --- |
| Disetujui | Hari itu dihitung sebagai kehadiran seperti biasa. |
| Ditolak | Hari itu berubah menjadi **tidak hadir** dan tidak dibayar. Catatan HR ditampilkan. |

> Foto absensi disimpan di penyimpanan privat. Hanya Anda sendiri, HR, dan
> manager divisi Anda yang dapat membukanya.

#### Clock out dan jam kerja

**Clock out**: tombol **Clock out** muncul di kanan atas setelah Anda clock in.
Jam kerja dihitung otomatis dari selisih clock in dan clock out.

Jam masuk standar adalah **08:00**. Lewat dari itu tercatat sebagai *Terlambat*
beserta jumlah menit keterlambatannya.

> **Bagi Mitra:** catatan jam kerja Anda menjadi dasar perhitungan pembayaran
> bila skema Anda per jam atau per hari. Pastikan clock in dan clock out lengkap.

### 3.2 Cuti & Izin Saya

Menu **Portal Saya → Cuti & Izin Saya**.

Di kanan layar ada kartu **Saldo cuti tahunan** yang menampilkan sisa hari Anda.
Seluruh entitas — Probation, PKWT, maupun Mitra — memiliki kuota. Bila suatu saat HR
menonaktifkan hak cuti untuk entitas tertentu, kartu ini menjelaskan alasannya dan
Anda tetap dapat mengajukan izin sakit atau izin tanpa gaji.

Cara mengajukan:

1. Pilih **Jenis** — pilihan yang tersedia menyesuaikan hak entitas Anda.
2. Isi tanggal **Mulai** dan **Selesai**. Total hari dihitung otomatis.
3. Isi **Alasan** (opsional tapi disarankan).
4. Klik **Kirim pengajuan**. Status awal *pending*, menunggu persetujuan atasan.

Pengajuan berstatus *pending* masih bisa dibatalkan lewat tombol **Batalkan** di
tabel riwayat. Setelah disetujui atau ditolak, pengajuan tidak dapat diubah lagi.

### 3.3 Slip Gaji Saya

Menu **Portal Saya → Slip Gaji Saya**. Menampilkan 24 periode terakhir.

Klik **Unduh slip** (karyawan) atau **Unduh voucher** (mitra) untuk memperoleh PDF.
Anda hanya dapat mengunduh dokumen milik sendiri.

**Isi slip gaji karyawan** dibagi tiga bagian:

* **A. Penerimaan** — gaji pokok, tunjangan tetap (beserta persentasenya), dan upah
  lembur lengkap dengan jumlah jam serta tarif per jamnya.
* **B. Potongan** — PPh 21, dan baris BPJS yang bernilai Rp 0 disertai keterangan
  berapa yang *seharusnya* menjadi porsi Anda.
* **C. Iuran BPJS yang dibayarkan perusahaan** — tabel per program (Kesehatan, JHT,
  JKM, JKK, JP) dengan persentase dan nominal porsi perusahaan maupun porsi pekerja
  yang ditalangi. Bagian ini **bukan pengurang** gaji Anda.

**Isi voucher mitra** menyesuaikan skemanya. Untuk skema penjualan ada dua slip:
slip gaji merinci dasar gaji (uang makan atau bonus) beserta perhitungan prorata
hari hadir, sedangkan slip insentif merinci tiap produk yang terjual.

### 3.4 Knowledge Center

Menu **Portal Saya → Knowledge Center**. Terbuka untuk semua peran.

**Bulletin pengumuman** di kolom kiri. Pengumuman yang disematkan muncul paling atas
dalam kotak tersendiri. Label kategori membedakan **Informasi**, **Kebijakan**, dan
**Penting**.

**SOP & Peraturan** di kolom kanan. Cari berdasarkan judul atau saring per jenis
dokumen (SOP, Peraturan Perusahaan, Panduan, Formulir), lalu klik tautan unduh.

> Isi halaman ini **disesuaikan dengan Anda**. Sebagian pengumuman dan dokumen hanya
> ditujukan untuk divisi atau entitas kerja tertentu, jadi daftar yang Anda lihat bisa
> berbeda dari rekan lain — itu normal, bukan kesalahan sistem.

### 3.5 Pinjam Inventaris

Menu **Portal Saya → Pinjam Inventaris**.

Halaman ini dipakai untuk meminjam aset perusahaan — laptop, proyektor, kendaraan
operasional, dan sebagainya.

**Mengajukan pinjaman**

1. Pilih **Aset**. Angka dalam kurung menunjukkan berapa unit yang masih tersedia.
2. Isi **Jumlah** dan **Rencana kembali**.
3. Tulis **Keperluan** sejelas mungkin — ini yang dinilai HR.
4. Klik **Kirim pengajuan**.

**Alur status**

| Status | Artinya |
| --- | --- |
| Menunggu persetujuan | Pengajuan terkirim, HR belum memutuskan. Masih bisa Anda batalkan. |
| Disetujui | Unit sudah dikunci untuk Anda, tapi barang belum berpindah tangan. |
| Sedang dipinjam | Barang sudah diserahkan. Kembalikan sebelum jatuh tempo. |
| Sudah dikembalikan | Selesai. |
| Ditolak | HR menolak; alasannya tertera di bawah pengajuan. |

Pinjaman yang lewat jatuh tempo diberi tanda merah **telat N hari**, baik di
halaman Anda maupun di konsol HR.

> **Penting saat resign:** pinjaman yang belum tuntas **menahan proses clearance**.
> Paklaring tidak dapat diterbitkan sebelum seluruh aset dikembalikan.

---

## 4. Panduan Manager / Atasan

Selain portal mandiri, Anda memperoleh dua menu tambahan. **Keduanya otomatis
terbatas pada divisi Anda** — Anda tidak akan melihat data divisi lain.

### 4.1 Rekap Absensi

Menu **Manajemen → Rekap Absensi**.

Lima kartu di atas meringkas periode terpilih: hadir tepat waktu, terlambat, tanpa
keterangan, jumlah absensi yang ditandai *fake GPS*, dan jumlah absensi mode unggah
yang **menunggu verifikasi**.

Filter tersedia di satu baris: rentang tanggal, nama/NIK, divisi, entitas, status,
metode absen, dan status verifikasi. Centang **Tampilkan hanya yang ditandai fake GPS**
untuk menyaring absensi yang perlu ditelusuri.

> **Tentang tanda fake GPS:** absensi yang mencurigakan **tetap tercatat**, hanya
> diberi tanda merah agar dapat Anda konfirmasi ke karyawan bersangkutan. Sistem tidak
> menolaknya diam-diam supaya jejaknya tetap bisa ditelusuri.

#### Memverifikasi absensi mode unggah

Kolom **Metode & verifikasi** memperlihatkan cara karyawan absen. Baris bertanda
*Unggah foto* dan *Menunggu verifikasi HR* menampilkan:

* alasan yang ditulis karyawan (dalam tanda kutip),
* jaraknya dari kantor bila ia berada di luar radius,
* tautan **Lihat foto** untuk membuka foto yang diunggah,
* tombol **Setujui** dan **Tolak**.

Cara tercepat menemukannya: pilih **Menunggu verifikasi HR** pada filter verifikasi.

| Tombol | Akibatnya |
| --- | --- |
| Setujui | Hari itu dihitung sebagai kehadiran (present/late sesuai jam masuk). |
| Tolak | Hari itu diubah menjadi **absent**, jam kerja dan keterlambatan dinolkan, sehingga payroll otomatis tidak membayarnya. Alasan penolakan wajib diisi. |

> Absensi mode kamera langsung berstatus *Terverifikasi otomatis* dan tidak
> memerlukan — dan tidak menerima — keputusan Anda.

### 4.2 Approval Cuti

Menu **Manajemen → Approval Cuti**.

Pengajuan berstatus *pending* memiliki dua tombol di kolom Tindakan:
**Setujui** dan **Tolak**. Setelah diputuskan, status berubah dan tombol hilang.

Kartu **Menunggu persetujuan** di atas menunjukkan berapa banyak yang butuh
tindakan Anda.

---

## 5. Panduan HR / Super Admin

### 5.1 Data Tenaga Kerja

Menu **Manajemen → Tenaga Kerja**.

**Menambah karyawan baru:** klik **Tambah**, isi dua bagian form:

* **Identitas** — NIK (wajib, harus unik), nama, kontak, jabatan, divisi.
* **Entitas kerja & kontrak** — bagian yang paling menentukan. Saat Anda memilih
  entitas, muncul keterangan hak yang menyertainya. Tanggal berakhir kontrak sebaiknya
  diisi agar karyawan masuk ke peringatan H-30.

Untuk **Mitra**, isi gaji pokok `0` — pembayarannya diatur terpisah di menu
Skema Mitra.

**Melihat detail:** klik nama karyawan. Di kanan ada kartu **Hak berdasarkan entitas**
yang merangkum apakah cuti tahunan, BPJS, dan PPh 21 berlaku untuk orang tersebut —
berguna saat karyawan bertanya soal potongan gajinya.

### 5.2 Entitas Kerja

Menu **Manajemen → Entitas Kerja**. Di sinilah aturan dasar diatur.

Klik **Ubah** pada suatu entitas untuk mengatur:

* **Berhak cuti tahunan** — bila dimatikan, pengajuan cuti tahunan ditolak sistem.
* **Didaftarkan BPJS** — bila dimatikan, potongan dan kontribusi BPJS menjadi nol.
* **Kuota cuti tahunan** dan **durasi kontrak**.

> ⚠️ Perubahan di sini berlaku untuk **semua** karyawan dengan entitas tersebut, dan
> memengaruhi perhitungan payroll periode berikutnya. Kolom "Aktif" menunjukkan berapa
> orang yang terdampak.

Entitas yang masih dipakai tenaga kerja tidak dapat dihapus.

### 5.3 Payroll

Menu **Manajemen → Payroll**.

**Menjalankan penggajian:**

1. Pada kartu **Jalankan periode penggajian**, pilih bulan dan tahun.
2. Klik **Jalankan payroll**. Sistem menghitung seluruh tenaga kerja aktif.
3. Hasilnya dilaporkan: berapa slip dibuat, diperbarui, dilewati, dan total netto.

> Slip yang sudah berstatus **paid tidak akan ditimpa**, kecuali Anda mencentang
> **Timpa slip yang sudah dibayar**. Ini pengaman agar penggajian yang sudah
> dibayarkan tidak berubah tanpa sengaja.

**Alur status slip:** `draft` → `approved` → `paid`. Ubah lewat dropdown di halaman
detail slip.

Klik nama karyawan untuk melihat rincian perhitungan — penerimaan, potongan, dan
take home pay, termasuk keterangan bila BPJS tidak berlaku untuk entitasnya.

### 5.4 Skema Mitra

Menu **Manajemen → Skema Mitra**.

Kartu **Belum punya skema** penting diperhatikan: **mitra tanpa skema akan dilewati
saat payroll dijalankan** — mereka tidak akan dibayar. Centang **Hanya yang belum
diatur** untuk menemukannya.

Klik **Atur** atau **Ubah**, lalu pilih tipe skema:

| Tipe | Kuantitas diambil dari |
| :--- | :--- |
| **Hourly Rate** | Otomatis — total jam kerja dari absensi |
| **Daily Rate** | Otomatis — jumlah hari hadir |
| **Fixed Project Fee** | Dibayar penuh satu kali per periode |
| **Deliverable / Milestone** | Persentase penyelesaian |
| **Unit / Output** | Diisi manual |
| **Kompensasi Penjualan** | Uang makan + insentif per unit + bonus tier UMP — unit diinput di menu Penjualan Mitra (bagian 5.5) |

Lengkapi tarif, satuan, skema pajak, dan persentase pajaknya. Tunjangan transport
bersifat opsional dan ditambahkan ke bruto setiap periode.

### 5.5 Penjualan Mitra

Menu **Manajemen → Penjualan Mitra**. Dipakai bila ada mitra dengan skema
**Kompensasi Penjualan**.

**Katalog produk** di kolom kanan — tiap produk punya insentif per unit terjual
(mis. EX2 Rp 500.000, EX5 Rp 2.000.000, Starray Rp 3.000.000). Klik **Tambah** untuk
produk baru, atau **Ubah** untuk menyesuaikan insentifnya. Produk yang sudah terpakai
pada catatan penjualan tidak dapat dihapus — nonaktifkan saja agar riwayatnya utuh.

**Input unit terjual:** pilih bulan dan tahun, lalu isi jumlah unit tiap produk pada
kartu mitra bersangkutan. Total unit, insentif, dan tier bonus dihitung langsung di
layar sebelum disimpan.

> Setelah menyimpan, **jalankan ulang payroll periode itu** agar angkanya masuk ke
> slip. Sistem tidak menghitung ulang slip yang sudah terbit secara otomatis.

Setiap periode menghasilkan **dua slip terpisah**:

**Slip 1 — Gaji.** Isinya salah satu, bukan keduanya:

| Kondisi | Gaji bulanan |
| :--- | :--- |
| Belum memenuhi tier (0–1 unit) | Uang makan & transport Rp 1.000.000 |
| Memenuhi tier | Bonus **menggantikan** uang makan: 2 unit → 50% UMP, 3 unit → 75%, 4 unit → 100% |

Nilai itu lalu diprorata: **÷ 26 hari kerja × hari hadir**. Slip ini tidak dipotong pajak.

> **Bonus menimpa, bukan menambah.** Mitra yang mencapai target tidak menerima
> uang makan *dan* bonus sekaligus — bonusnya menjadi gaji bulanannya. Karena semua
> tier bernilai di atas Rp 1.000.000, mencapai target selalu berarti gaji naik.

**Slip 2 — Insentif penjualan.** Unit terjual × insentif tiap produk, **tidak diprorata**
hari hadir, dipotong pajak 50% × 2,5%. Slip ini hanya terbit bila ada penjualan.

Contoh mitra yang menjual 4 unit (2 EX2 + 1 EX5 + 1 Starray) dengan hadir 18 dari 26 hari:

| Slip | Perhitungan | Jumlah |
| :--- | :--- | :--- |
| Gaji | 100% × 3.921.000 × 18/26 | 2.714.538 |
| Insentif | 6.000.000 − pajak 75.000 | 5.925.000 |

Nilai uang makan, jumlah hari kerja, UMP acuan, tier bonus, dan tarif pajak diatur
per mitra pada menu **Skema Mitra**.

### 5.6 Lowongan

Menu **Manajemen → Lowongan**. Inilah sumber data Portal Karier.

Klik **Buat lowongan**, isi judul, kategori entitas yang ditawarkan, divisi, lokasi,
deskripsi, kualifikasi, dan kuota.

**Alur status:**

| Status | Artinya |
| :--- | :--- |
| **Draft** | Hanya terlihat internal. Aman untuk menyiapkan deskripsi. |
| **Open** | Tampil di Portal Karier dan menerima lamaran. |
| **Closed** | Hilang dari portal; pelamar yang sudah masuk tetap ada di pipeline. |

Gunakan tombol **Publikasikan** / **Tutup** untuk berpindah status tanpa membuka form.
Tanggal publikasi terisi otomatis saat pertama kali dibuka.

> Lowongan yang sudah memiliki pelamar **tidak dapat dihapus** — tutup saja, agar
> riwayat rekrutmen tidak ikut terhapus. Tombol Hapus hanya muncul bila pelamarnya
> masih nol.

### 5.7 Rekrutmen (ATS)

Menu **Manajemen → Rekrutmen (ATS)**.

Papan pipeline mengelompokkan pelamar ke lima tahap:
`Applied → Screening → Interview → Offering → Hired`. Pelamar yang ditolak
disimpan terpisah di bagian bawah — klik **Tampilkan** untuk melihatnya.

**Memindahkan tahap:** setiap kartu pelamar punya dropdown kecil di pojok kanan bawah
(bertanda `→`). Pilih tahap tujuan, kartu langsung berpindah kolom. Tombol
**Lihat CV** juga tersedia di kartu bila pelamar melampirkannya.

**Melihat detail pelamar:** klik namanya. Di halaman detail tersedia:

* **Profil Kandidat** — termasuk tombol **Download CV**.
* **Ubah Tahap** — daftar tahap; yang aktif ditandai bulatan penuh.
* **Catatan Interview** — tulis observasi tiap tahap; tercatat beserta penulis dan waktu.
* **Riwayat Perpindahan Tahap** — jejak audit siapa memindahkan apa dan kapan.
* **Dokumen** — Offering Letter (muncul di tahap Offering) dan Kontrak (setelah dikonversi).

#### Mengangkat pelamar menjadi karyawan

Ada tiga jalan menuju form konversi, semuanya menghasilkan hal yang sama:

* Pilih tahap **Hired** pada dropdown kartu di papan pipeline.
* Klik tombol **Konversi ke Karyawan** di halaman detail kandidat.
* Pilih tahap **Hired** pada panel *Ubah Tahap* di halaman detail.

Pada form **Konversi ke Karyawan**:

1. Pilih **Entitas kerja** — Probation, PKWT 3/6/12 bulan, atau Mitra.
2. Isi divisi, jabatan, dan gaji pokok.
3. Bila memilih **Mitra**, muncul bagian Skema Pembayaran yang **wajib diisi**,
   termasuk tanggal berakhir kontrak (mitra tidak punya durasi baku).
4. Klik **Konversi**.

Sistem membuat data karyawan otomatis dari data pelamar — tanpa input ulang — lengkap
dengan NIK baru dan tanggal berakhir kontrak sesuai durasi entitas.

### 5.8 Proses Keluar & Paklaring

Menu **Manajemen → Proses Keluar**. Dipakai saat karyawan mengundurkan diri,
kontraknya berakhir, di-PHK, atau pensiun.

Prosesnya sengaja **dua tahap** agar surat tidak terbit sebelum waktunya:

**Tahap 1 — Draft.** Klik **Catat proses keluar**, pilih karyawan (hanya yang masih
aktif dan belum punya catatan keluar), lalu isi:

* **Jenis** — Mengundurkan Diri / Kontrak Berakhir / PHK / Pensiun.
* **Tanggal pengajuan** — untuk pengunduran diri; kosongkan bila tidak relevan.
* **Hari kerja terakhir** — dipakai menghitung masa kerja pada paklaring.
* **Alasan** — dicatat sebagai riwayat.
* **Catatan internal** — hasil exit interview atau serah terima; **tidak dicetak**
  pada paklaring.

Pada tahap ini karyawan **masih berstatus aktif** dan datanya masih bisa diperbaiki.

**Tahap 2 — Tuntaskan.** Klik **Tuntaskan & terbitkan**.

Sebelum apa pun terjadi, sistem menjalankan **pengecekan clearance**: bila karyawan
masih memiliki peminjaman inventaris yang belum tuntas, proses **ditolak** dan nama
asetnya disebutkan. Kartu draft juga menampilkan peringatan merah *"Clearance
tertahan: N peminjaman inventaris belum dikembalikan"* sejak awal, sehingga Anda tahu
sebelum menekan tombolnya. Selesaikan pengembaliannya di menu **Inventaris**, lalu
ulangi.

Bila clearance bersih, sistem otomatis:

* mengubah status karyawan menjadi `resigned` (resign/PHK/pensiun) atau `expired`
  (kontrak berakhir);
* menerbitkan nomor paklaring berformat `001/PKL-HR/VIII/2026`, berurutan per tahun;
* mengaktifkan tombol **Paklaring PDF**.

> **Paklaring dapat dicetak ulang kapan saja dengan nomor yang sama.** Mantan karyawan
> sering membutuhkannya lagi bertahun-tahun kemudian — untuk klaim JHT BPJS
> Ketenagakerjaan, melamar kerja, atau pengajuan kredit. Nomor tidak pernah berubah
> agar surat lama tetap dapat diverifikasi.

Isi surat menyesuaikan jenisnya: untuk resign, kontrak berakhir, dan pensiun
dicantumkan keterangan kinerja baik; untuk PHK kalimat itu tidak disertakan.

**Salah catat?** Klik **Buka kembali** — karyawan kembali aktif dan data bisa diperbaiki.
Nomor surat sengaja dipertahankan supaya paklaring yang terlanjur beredar tetap terlacak.
Draft yang belum dituntaskan boleh dihapus; yang sudah tuntas tidak bisa.

Kartu **Kontrak habis H-30** di atas menunjukkan karyawan yang kontraknya segera
berakhir tapi belum dibuatkan proses keluar.

### 5.9 Kelola Knowledge Center

Menu **Manajemen → Kelola Knowledge**.

**Membuat pengumuman:** klik **Buat pengumuman**, isi judul dan isi, pilih kategori,
lalu tentukan audiens:

| Ditujukan kepada | Yang melihat |
| :--- | :--- |
| **Seluruh perusahaan** | Semua orang |
| **Divisi tertentu** | Hanya anggota divisi tersebut |
| **Entitas kerja tertentu** | Hanya Probation, PKWT, atau Mitra |

Dua penanda tambahan:

* **Sematkan di atas** — pengumuman muncul di kotak terpisah paling atas.
* **Terbitkan sekarang** — bila tidak dicentang, pengumuman tersimpan sebagai *draft*
  dan belum terlihat karyawan. Terbitkan belakangan lewat tombol **Terbitkan**.

**Mengunggah dokumen:** klik **Unggah dokumen** pada kartu Dokumen. Isi judul,
deskripsi, jenis, versi, lalu pilih berkas (PDF/Word/Excel/PowerPoint, maks 10 MB) dan
tentukan audiensnya seperti di atas.

Kolom **Unduhan** pada tabel dokumen menunjukkan berapa kali berkas itu diambil —
berguna untuk menilai SOP mana yang benar-benar dibaca.

> Dokumen disimpan di penyimpanan privat. Karyawan hanya dapat mengunduh lewat sistem,
> dan permintaan unduh diperiksa ulang terhadap audiens — bukan sekadar disembunyikan
> dari daftar.

Gunakan tombol **Lihat sebagai pembaca** untuk memastikan hasilnya tampil sebagaimana
mestinya.

### 5.10 Inventaris & Peminjaman

Menu **Manajemen → Inventaris**. Satu halaman berisi dua hal: katalog aset dan
seluruh peminjaman.

**Empat kartu ringkasan:** jenis aset & total unit, unit yang sedang dipinjam,
pengajuan yang menunggu keputusan, dan pinjaman yang lewat jatuh tempo.

#### Mengelola katalog aset

Klik **Tambah aset**, lalu isi panel di kanan:

* **Kode aset** — wajib dan unik, mis. `AST-LP-001`.
* **Jumlah unit** — untuk barang serial (laptop, kendaraan) isi 1; untuk barang
  generik (kursi lipat, modem) isi jumlah sebenarnya. Sistem menghitung sendiri
  berapa yang masih tersedia.
* **Kondisi** — Baik / Rusak ringan / Rusak berat.
* **Status** — Aktif (boleh dipinjam), Perbaikan, atau Dihapus.
* Merek, nomor seri, lokasi penyimpanan, harga & tanggal perolehan, catatan.

Tabel **Katalog aset** menampilkan kolom *Tersedia* dalam bentuk `tersedia / total`.
Tombol **Ubah** dan **Hapus** ada di ujung kanan tiap baris.

Dua pagar pengaman: aset yang masih memiliki pinjaman berjalan **tidak dapat dihapus**,
dan jumlah unitnya **tidak dapat diturunkan** di bawah jumlah yang sedang dipinjam.

#### Memproses peminjaman

Daftar **Peminjaman** menaruh pengajuan yang menunggu keputusan di paling atas.
Tombol yang muncul mengikuti status saat ini — alur tidak bisa dilompati:

| Status sekarang | Tombol yang tersedia |
| --- | --- |
| Menunggu persetujuan | **Setujui**, **Tolak** |
| Disetujui | **Serahkan barang**, **Tolak** |
| Sedang dipinjam | **Catat pengembalian**, **Tandai hilang** |
| Selesai / ditolak / hilang | — (riwayat, tidak bisa diubah) |

Menekan salah satu tombol membuka panel kecil untuk catatan; **Serahkan barang** dan
**Catat pengembalian** juga meminta **kondisi barang**.

Yang dilakukan sistem di balik layar:

* **Setujui** mengunci unitnya. Bila stok ternyata sudah habis diambil pengajuan lain,
  persetujuan ditolak dengan pesan sisa stok — jadi satu unit tidak pernah dijanjikan
  ke dua orang.
* **Catat pengembalian** melepas unit kembali ke stok. Bila kondisinya lebih buruk dari
  catatan aset, kondisi master ikut turun; kondisi *Rusak berat* otomatis memindahkan
  aset ke status **Perbaikan**.
* **Tandai hilang** mengurangi jumlah unit secara permanen. Bila habis, aset menjadi
  **Dihapus**.

**Mencatat pinjaman atas nama pegawai:** klik **Catat pinjaman** di kanan atas — isian
ini langsung berstatus disetujui, dipakai saat serah terima terjadi di luar sistem.

Filter status memiliki pilihan tambahan **Lewat jatuh tempo** untuk menagih
pengembalian.

Ekspor tersedia lewat tombol **Export Data** (Excel/CSV/PDF), berisi seluruh riwayat
peminjaman beserta kolom keterlambatan.

---

## 6. Portal Karier — untuk pelamar

Alamat: **`/karier`** — dapat dibagikan ke publik, tidak perlu login.

Pelamar dapat menyaring lowongan per kategori, membuka detail, lalu mengisi form:
nama, email, telepon, dan unggah CV (PDF/DOC/DOCX, maksimal 5 MB).

Lamaran yang masuk langsung muncul di pipeline ATS pada tahap *Applied*.

> **Kerahasiaan CV:** berkas CV disimpan di penyimpanan privat dan hanya dapat diunduh
> lewat sistem oleh HR. CV tidak dapat diakses melalui tautan publik.

---

## 7. Ekspor data

Hampir setiap halaman memiliki tombol **Export data** di kanan atas. Cara kerjanya
seragam:

1. Atur filter di halaman terlebih dahulu (rentang tanggal, divisi, status, dll).
2. Klik **Export data**, pilih laporan, lalu pilih format.
3. Berkas terunduh sesuai **filter yang sedang aktif** — isi berkas sama dengan yang
   tampil di layar.

| Format | Cocok untuk |
| :--- | :--- |
| **Excel (.xlsx)** | Laporan detail yang masih akan diolah |
| **CSV** | Diunggah ke sistem lain, mis. file transfer bank |
| **PDF** | Dokumen cetak atau lampiran resmi |

Laporan yang tersedia menyesuaikan peran Anda. Setiap pengunduhan tercatat dalam
audit log beserta pengunduh, waktu, dan filternya.

---

## 8. Pertanyaan yang sering muncul

**Kamera saya tidak bisa dinyalakan, bagaimana cara absen?**
Pakai opsi **Unggah foto** pada halaman Absensi Saya. Ambil foto dengan aplikasi kamera
biasa, unggah berkasnya, dan tulis alasannya. Absensi tetap tercatat, hanya perlu
disetujui HR lebih dulu.

**Saya kerja di lapangan, jauh dari kantor. Apakah bisa absen?**
Bisa, lewat opsi **Unggah foto**. Opsi ini tidak memblokir Anda karena berada di luar
radius — jaraknya hanya dicatat dan ditampilkan kepada HR saat memverifikasi. Opsi
kamera langsung tetap menolak absensi dari luar radius.

**Absensi unggahan saya ditolak HR. Apa akibatnya?**
Hari itu berubah menjadi *tidak hadir*, sehingga tidak dihitung sebagai hari kerja
maupun dibayar. Alasan penolakan HR ditampilkan pada kartu status Anda.

**Kenapa saya tidak bisa diproses keluar padahal sudah resign?**
Kemungkinan besar masih ada aset perusahaan yang belum Anda kembalikan. Cek halaman
**Pinjam Inventaris**; selama masih ada pinjaman berjalan, paklaring tidak dapat terbit.

**Pengajuan pinjaman saya sudah disetujui, tapi barangnya belum ada.**
Status *Disetujui* berarti unitnya sudah dikunci untuk Anda tetapi serah terima fisik
belum dilakukan. Status berubah menjadi *Sedang dipinjam* setelah HR mencatat serah
terimanya.

**Sebagai Probation/Mitra, apakah saya benar punya cuti tahunan?**
Ya. Sejak kebijakan terbaru ketiga entitas memiliki kuota — Probation 3 hari, PKWT
3/6/12 hari sesuai kontrak, dan Mitra 12 hari. Sisa kuota Anda tertera pada halaman
Cuti & Izin Saya. Bila suatu saat pengajuan ditolak sistem, berarti HR menonaktifkan
hak cuti untuk entitas Anda; kartu saldo akan menjelaskannya.

**Kenapa pengajuan cuti saya ditolak karena melebihi kuota?**
Total hari yang diajukan lebih besar dari sisa kuota tahun berjalan. Pesan
penolakannya menyebutkan sisa kuota Anda. Ajukan dengan durasi lebih pendek, atau
gunakan izin tanpa gaji.

**Kenapa slip gaji saya tidak ada potongan BPJS?**
Karena iuran BPJS **ditanggung penuh perusahaan** — termasuk porsi pekerja. Anda tetap
terdaftar sebagai peserta; yang nol hanyalah potongannya. Nilai yang dibayarkan
perusahaan tercantum sebagai keterangan pada slip.

**Bonus penjualan saya tidak penuh, kenapa?**
Bonus diprorata terhadap hari hadir. Hadir 20 dari 26 hari kerja berarti Anda menerima
20/26 bagian. Uang makan & transport mengikuti aturan yang sama; hanya insentif per
unit yang dibayar penuh tanpa melihat kehadiran.

**Saya capai target, tapi kenapa uang makan saya hilang dari slip?**
Memang begitu aturannya: bonus pencapaian **menggantikan** uang makan & transport
sebagai gaji bulanan, bukan ditambahkan. Karena semua tier bernilai di atas
Rp 1.000.000, gaji Anda tetap naik dibanding bulan tanpa penjualan.

**Kenapa saya menerima dua slip dalam satu bulan?**
Gaji dan insentif penjualan sengaja dipisah. Slip gaji berisi uang makan atau bonus
pencapaian; slip insentif berisi komisi per unit terjual beserta potongan pajaknya.

**Saya sudah input penjualan tapi slip mitra belum berubah.**
Jalankan ulang payroll untuk periode tersebut lewat menu Payroll. Slip berstatus
*paid* tidak akan ditimpa kecuali opsi timpa dicentang.

**Kenapa ada mitra yang tidak muncul di hasil payroll?**
Mitra tanpa skema pembayaran akan dilewati. Buka **Skema Mitra**, centang
**Hanya yang belum diatur**, lalu lengkapi skemanya dan jalankan ulang payroll.

**Clock in saya ditolak, katanya di luar radius.**
Anda berada terlalu jauh dari titik kantor. Halaman menampilkan jarak persisnya.
Pastikan GPS perangkat aktif dan Anda berada di area kantor.

**Absensi saya ditandai "fake GPS", apa artinya?**
Sistem mendeteksi indikasi lokasi tidak wajar — misalnya akurasi GPS yang mustahil
atau perpindahan terlalu cepat. Absensi tetap tercatat; HR akan mengonfirmasi.
Kalau Anda absen secara wajar, cukup jelaskan saat ditanya.

**Kenapa memilih tahap Hired selalu memunculkan form, bukan langsung berpindah?**
Karena *Hired* berarti orang tersebut menjadi karyawan — jadi datanya harus dibuat
sekalian. Form itu yang membuat record karyawan, NIK, dan kontraknya. Bila Anda hanya
ingin menandai tanpa mengangkat, gunakan tahap *Offering* dulu.

**Pelamar berstatus Rejected tidak bisa saya konversi.**
Disengaja. Kembalikan dulu ke tahap seleksi (mis. Offering), baru konversi — agar
riwayat perpindahannya jelas.

**Saya menjalankan payroll dua kali, apakah slip jadi dobel?**
Tidak. Slip yang sudah ada akan diperbarui, dan slip berstatus *paid* dilewati kecuali
Anda mencentang opsi timpa.

**Saya sudah menandai karyawan keluar, tapi statusnya masih aktif.**
Catatan proses keluar masih berstatus *draft*. Status karyawan baru berubah setelah
Anda klik **Tuntaskan & terbitkan** — itu juga saat nomor paklaring diterbitkan.

**Mantan karyawan minta paklaring lagi, apakah perlu dibuat ulang?**
Tidak. Buka menu Proses Keluar, cari namanya, lalu klik **Paklaring PDF**. Surat
tercetak dengan nomor dan isi yang sama persis seperti penerbitan pertama.

**Menu tertentu tidak muncul di sisi kiri.**
Menu menyesuaikan peran. Manager tidak melihat Payroll dan Tenaga Kerja; karyawan
hanya melihat Portal Saya (termasuk Knowledge Center). Hubungi HR bila Anda merasa
seharusnya punya akses.

**Rekan saya melihat pengumuman yang tidak ada di layar saya.**
Pengumuman dan dokumen dapat ditujukan ke divisi atau entitas kerja tertentu. Bila
konten itu bukan untuk kelompok Anda, sistem memang tidak menampilkannya.

---

*Panduan ini mengikuti versi sistem per 3 Agustus 2026 (Modul 1–5 aktif, termasuk skema kompensasi penjualan mitra). Untuk daftar fitur yang belum
tersedia, lihat bagian Batasan pada [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md).*
