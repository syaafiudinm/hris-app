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
| **Probation** (3 bulan) | 3 hari | Belum didaftarkan | Gaji pokok + tunjangan + lembur |
| **PKWT** (3/6/12 bulan) | 3 / 6 / 12 hari | Didaftarkan | Gaji pokok + tunjangan + lembur |
| **Mitra / Freelance** | 12 hari | Tidak ikut | Skema custom (per jam/hari/proyek/unit) |

Sejak kebijakan terbaru, **ketiga entitas berhak cuti tahunan** dengan kuota
proporsional terhadap durasi kontrak. Aturan BPJS tidak ikut berubah: Probation dan
Mitra tetap dikecualikan.

Perbedaan ini **ditegakkan otomatis oleh sistem**, bukan bergantung pada ketelitian
petugas. Slip gaji karyawan probation, misalnya, tidak akan pernah memotong BPJS.

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

**Clock in** memerlukan dua hal: titik GPS di dalam radius kantor, dan foto selfie.

1. Klik **Ambil lokasi**. Izinkan akses lokasi saat browser meminta.
   Sistem menampilkan jarak Anda ke kantor terdekat:
   * Hijau bertanda centang = di dalam radius, boleh lanjut.
   * Merah = di luar radius; clock in akan ditolak.
2. Klik **Nyalakan kamera**, lalu **Ambil foto**. Bila hasilnya kurang baik, klik
   **Ulangi foto**.
3. Klik **Kirim clock in**.

Tombol **Kirim clock in** baru aktif setelah lokasi valid *dan* foto sudah diambil.
Jika masih nonaktif, tulisan di bawahnya menjelaskan apa yang kurang.

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

---

## 4. Panduan Manager / Atasan

Selain portal mandiri, Anda memperoleh dua menu tambahan. **Keduanya otomatis
terbatas pada divisi Anda** — Anda tidak akan melihat data divisi lain.

### 4.1 Rekap Absensi

Menu **Manajemen → Rekap Absensi**.

Empat kartu di atas meringkas periode terpilih: hadir tepat waktu, terlambat, tanpa
keterangan, dan jumlah absensi yang ditandai *fake GPS*.

Filter tersedia di satu baris: rentang tanggal, nama/NIK, divisi, entitas, dan status.
Centang **Tampilkan hanya yang ditandai fake GPS** untuk menyaring absensi yang perlu
diverifikasi.

> **Tentang tanda fake GPS:** absensi yang mencurigakan **tetap tercatat**, hanya
> diberi tanda merah agar dapat Anda konfirmasi ke karyawan bersangkutan. Sistem tidak
> menolaknya diam-diam supaya jejaknya tetap bisa ditelusuri.

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

Lengkapi tarif, satuan, skema pajak, dan persentase pajaknya. Tunjangan transport
bersifat opsional dan ditambahkan ke bruto setiap periode.

### 5.5 Lowongan

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

### 5.6 Rekrutmen (ATS)

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

### 5.7 Kelola Knowledge Center

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
Karyawan masa percobaan dan mitra belum didaftarkan BPJS, sehingga potongannya nol.
Slip gaji mencantumkan keterangan ini secara eksplisit.

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

**Menu tertentu tidak muncul di sisi kiri.**
Menu menyesuaikan peran. Manager tidak melihat Payroll dan Tenaga Kerja; karyawan
hanya melihat Portal Saya (termasuk Knowledge Center). Hubungi HR bila Anda merasa
seharusnya punya akses.

**Rekan saya melihat pengumuman yang tidak ada di layar saya.**
Pengumuman dan dokumen dapat ditujukan ke divisi atau entitas kerja tertentu. Bila
konten itu bukan untuk kelompok Anda, sistem memang tidak menampilkannya.

---

*Panduan ini mengikuti versi sistem per 2 Agustus 2026 (Modul 1–5 aktif). Untuk daftar fitur yang belum
tersedia, lihat bagian Batasan pada [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md).*
