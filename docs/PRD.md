# 📋 SiJaProt — Product Requirements Document (PRD)
**Versi:** 1.0 (Final & Locked)  
**Stakeholder:** Bagian Protokol Kemendikdasmen RI  
**Tanggal:** 20 Oktober 2026

---

## 1. Executive Summary & Objective

**SiJaProt (Sistem Jadwal Protokol)** adalah platform web internal untuk mengotomasi dan mendistribusikan penugasan pegawai protokol secara transparan, adil, dan seimbang.

Sistem mengatasi masalah:
1. Penjadwalan manual yang memicu rasa ketidakadilan terkait honorarium penugasan (kegiatan dibayar vs tidak dibayar, hari kerja vs weekend/libur).
2. Potensi bentrok tugas dan kelelahan (burnout) tanpa jeda istirahat yang cukup.
3. Hak libur bersama keluarga yang kerap terabaikan di akhir pekan.

---

## 2. Master Data Entities

### 2.1 Master User (Auth & RBAC)
- Menggunakan standard Laravel Breeze / Fortify.
- Role:
  - `Super Admin`: Akses penuh konfigurasi sistem, user management, audit trail.
  - `Admin Protokol (Kasubbag)`: Mengelola master protokol, kegiatan, eksekusi & override auto-scheduling, export laporan.
  - `Operator`: Input draft kegiatan dan monitoring dashboard kalender.

### 2.2 Master Pegawai (Protokol)
- Atribut: Nama Lengkap & Gelar, Nomor WhatsApp/HP, Status (`active` / `inactive`).
- Protokol yang berstatus `inactive` secara otomatis **dieksklusikan** dari pool algoritma penjadwalan.

### 2.3 Master Jenis Kegiatan (Template Reference)
Menjadi acuan otomatis saat pembuatan kegiatan baru:
1. **Tipe Pembayaran**:
   - `paid` (Dibayar / Berhonorarium)
   - `unpaid` (Tidak Dibayar / Rapat Rutin Internal)
2. **Kategori Waktu Pembayaran** (jika `paid`):
   - `workday` (Hari Kerja: Senin – Jumat)
   - `offday` (Luar Hari Kerja: Sabtu, Minggu, atau Hari Libur Nasional)
3. **Wilayah / Lokasi**:
   - `dalam_kota`
   - `luar_kota` (Memicu aturan gap perjalanan dinas)

### 2.4 Master Hari Libur Nasional
- Didefinisikan secara manual oleh Admin per tahun buku kalender (misal: 17 Agustus, 28 Oktober, Hari Raya Keagamaan).
- Diperlakukan setara dengan akhir pekan (weekend) dalam hal perhitungan hak libur dan honorarium luar hari kerja.

---

## 3. Aturan Bisnis & Algoritma Penjadwalan (LOCKED)

### 3.1 Hard Rules (Wajib 100% Terpenuhi)
1. **H1 — Gap Waktu (Sistem Hybrid)**:
   - **Dalam Kota**: Pegawai yang selesai bertugas jam $X$ pada hari $Y$ diblokir dari penugasan berikutnya selama **minimal 24 jam penuh** terhitung dari waktu selesai kegiatan.
   - **Luar Kota**: Penugasan luar kota (multi-hari maupun 1 hari) mengunci status pegawai hingga hari kepulangan, plus **blokir penuh 1 hari kalender berikutnya ($H+1$)**.
   - **No Time Overlap**: Tidak boleh ada 2 kegiatan yang jadwal jamnya beririsan untuk pegawai yang sama.
2. **H2 — Minimum 1x Tugas per Bulan**:
   - Semua pegawai aktif wajib bertugas minimal 1 kali per bulan. Pegawai yang belum pernah bertugas bulan berjalan mendapat prioritas seleksi paling tinggi.
3. **H3 — Perlindungan Full Weekend ($H4$)**:
   - Pegawai yang bertugas di hari Sabtu **atau** Minggu, maka **seluruh Sabtu dan Minggu di akhir pekan berikutnya diblokir penuh** untuk memberikan hak istirahat bersama keluarga. Pegawai baru dapat bertugas weekend kembali pada minggu ke-2 setelahnya.
4. **H4 — Multi-Hari Luar Kota Dihitung 1 Penugasan (Opsi A)**:
   - Perjalanan dinas luar kota multi-hari (misal 3 hari) dihitung sebagai **1 penugasan** dalam statistik jumlah kegiatan, namun proteksi blokir kalender berlaku sepanjang durasi dinas + $H+1$.

### 3.2 Soft Rules (Target Keseimbangan Distribusi)
1. **S1 — Keseimbangan Tugas Dibayar vs Tidak Dibayar**:
   - Variansi selisih penugasan berbayar dan tidak berbayar antar pegawai dalam 1 bulan diupayakan $\le 1$ kegiatan.
2. **S2 — Keseimbangan Honor Hari Kerja vs Luar Kerja**:
   - Rasio kegiatan dibayar pada hari kerja vs luar hari kerja dibagi proporsional.
3. **S3 — Keseimbangan Penugasan Luar Kota**:
   - Menghindari monopoli penugasan luar kota oleh oknum protokol tertentu.
4. **S4 — Rotasi Protokol Terlama Belum Bertugas**:
   - Memberikan prioritas kepada pegawai yang memiliki interval istirahat terlama sejak penugasan terakhir.

### 3.3 Relaksasi Bertahap (Saat Pool Pegawai Kurang)
Jika kandidat yang lolos Hard Rules kurang dari kuota yang diminta, sistem secara otomatis merelaksasi aturan lunak secara berurutan:
- *Tahap 1*: Relaksasi kuota distribusi S4 (rotasi) dan S3 (luar kota).
- *Tahap 2*: Relaksasi rasio S1/S2 (keseimbangan honorarium).
- *Tahap 3*: Relaksasi H1 Luar Kota (jika mendesak, blokir $H+1$ dilonggarkan menjadi batas jam).
- *Tahap 4*: Jika tetap tidak mencukupi, sistem menampilkan peringatan ke Admin untuk melakukan penunjukan/override manual.

### 3.4 Override Manual & Audit Trail
- Admin berhak mengganti personil hasil auto-scheduling jika ada pegawai yang sakit/berhalangan.
- Setiap penggantian **wajib mengisi alasan override**, dan tercatat permanen di tabel audit log.

---

## 4. Dashboard & Pelaporan

1. **Dashboard Kalender 1–31**:
   - Tampilan grid kalender interaktif bulanan dengan indikator visual jenis kegiatan.
   - Panel klik harian menampilkan detail rundown dan protokol bertugas.
2. **Ekspor Laporan**:
   - Format **Microsoft Excel (`.xlsx`)** dan **Adobe PDF (`.pdf`)**.
   - Berisi matriks fairness, rekap total tugas dibayar/free, trip luar kota, dan status balance.
