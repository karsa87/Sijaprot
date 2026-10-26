# 📋 PRD v2.0 — Spesifikasi Perubahan & Penambahan Fitur SiJaProt
**Aplikasi:** SiJaProt (Sistem Jadwal Protokol Kemendikdasmen RI)  
**Versi Dokumen:** 2.0  
**Tanggal:** 26 Oktober 2026  
**Status:** Approved for Development  
**Target Pengguna:** Bagian Protokol Biro Umum Kemendikdasmen RI  

---

## 1. Executive Summary & Latar Belakang

Berdasarkan evaluasi operasional Bagian Protokol Kemendikdasmen RI, terdapat kebutuhan penyelarasan sistem penjadwalan terhadap dinamika kedinasan pemerintah terkini, antara lain:
1. **Fleksibilitas Manajemen Template**: Pengelola membutuhkan kapabilitas untuk menghapus atau menonaktifkan jenis kegiatan yang sudah usang (*deprecated*).
2. **Kategori Pembayaran Lebih Realistis**: Penganggaran acara kementerian kerap memiliki status kepastian honorarium yang bersifat tentatif, sehingga diperlukan status **"Kemungkinan Dibayar"** selain "Dibayar" dan "Tidak Dibayar".
3. **Pembedaan Tugas Kunjungan Kerja (Kunker)**: Kunjungan kerja pimpinan memiliki beban fisik, durasi, dan kehormatan dinas yang berbeda signifikan dari acara seremonial biasa.
4. **Penerapan Kebijakan Kerja *Work From Anywhere* (WFA) Hari Jum'at**: Perlindungan hak WFA dan rotasi yang adil agar personil yang bertugas saat WFA Jum'at tidak terbebani di akhir pekan (*weekend fatigue guard*).
5. **Pembaruan Formula Keseimbangan & Laporan**: Format pelaporan dinas disederhanakan dan difokuskan pada 7 metrik pokok kedinasan.

---

## 2. Rincian Scope Perubahan & Penambahan Fitur

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       FITUR BARU & PERUBAHAN SISTEM                         │
├────────────────────────────────┬────────────────────────────────────────────┤
│ 1. Hapus Master Jenis Kegiatan │ Safe Deletion / Soft Delete template       │
│ 2. Tipe Pembayaran Baru        │ Kemungkinan Dibayar (Conditional)          │
│ 3. Klasifikasi Kunker          │ Flag Kunjungan Kerja (Kunker vs Non-Kunker)│
│ 4. Kategori Hari WFA           │ Jum'at (WFA) + Deteksi Otomatis            │
│ 5. Aturan Algoritma Baru       │ • WFA-to-Weekend Block di minggu sama      │
│                                │ • Rotasi WFA Antar-Minggu                  │
│                                │ • Target Min 1x Kunker per bulan           │
│                                │ • Multidimensi Scoring (6 Variabel)        │
│ 6. Format Laporan Baru         │ 7 Metrik Spesifik (Web, Excel, PDF)        │
└────────────────────────────────┴────────────────────────────────────────────┘
```

---

### FITUR 1: Hapus Master Jenis Kegiatan (Template)

#### 1.1 Kebutuhan Bisnis
Admin Protokol dapat menghapus template jenis kegiatan yang sudah tidak relevan atau salah input dari menu Master Referensi.

#### 1.2 Aturan Integritas Data & Proteksi Relasi
Karena tabel `kegiatan` memiliki relasi *foreign key* `jenis_kegiatan_id` dengan kebijakan `restrictOnDelete()`:
1. **Jenis Kegiatan yang Belum Pernah Digunakan**:
   - Dapat dihapus secara permanen (*Hard Delete*) dari database.
2. **Jenis Kegiatan yang Sudah Memiliki Riwayat Kegiatan**:
   - Menggunakan mekanisme **Soft Delete** (`deleted_at`).
   - Template tidak lagi muncul pada opsi pilihan dropdown form kegiatan baru, namun seluruh arsip kegiatan masa lalu dan laporan historis tetap utuh (*referential integrity* terjaga 100%).
3. **Konfirmasi Pengguna**:
   - Menampilkan modal konfirmasi dengan peringatan jumlah kegiatan yang terkait sebelum eksekusi.

---

### FITUR 2: Penambahan Tipe Pembayaran "Kemungkinan Dibayar"

#### 2.1 Kategori Pembayaran Baru
Menambahkan opsi ketiga pada tipe pembayaran:
1. `paid` (**Dibayar / Berhonorarium**): Anggaran honorarium sudah definitif tersedia.
2. `conditional` (**Kemungkinan Dibayar / Tentatif**): Kegiatan berpeluang mendapatkan honorarium bergantung pada ketersediaan DIPA atau disposisi pimpinan.
3. `unpaid` (**Tidak Dibayar / Rutin Internal**): Kegiatan tanpa honorarium (rapat internal, apel pagi, upacara rutin).

#### 2.2 Penyesuaian Komponen:
- **Enum `PaymentType`**: Menambahkan nilai `CONDITIONAL = 'conditional'`.
- **Master Referensi**: Pilihan 3 tombol radio pada form tambah/edit jenis kegiatan.
- **Form Kegiatan**: Auto-detection tipe pembayaran mendeteksi opsi "Kemungkinan Dibayar".
- **Badge Visual**: Warna badge khas (misal: *Sky Blue / Cyan*) untuk membedakan secara instan dari *Emerald* (Dibayar) dan *Slate* (Tidak Dibayar).

---

### FITUR 3: Pembedaan Kunjungan Kerja (Kunker)

#### 3.1 Kebutuhan Bisnis
Setiap kegiatan harus dapat diklasifikasikan secara tegas apakah merupakan agenda **Kunjungan Kerja (Kunker)** atau **Bukan Kunker**.

#### 3.2 Implementasi Data:
- Kolom `is_kunker` (Boolean: `true` / `false`) pada tabel `jenis_kegiatan` (sebagai template bawaan) dan pada tabel `kegiatan` (sebagai nilai aktual per agenda).
- **Default Behavior**:
  - Jika template jenis kegiatan bertipe Kunker dipilih, switch/toggle "Kunjungan Kerja (Kunker)" otomatis aktif (`true`).
  - Admin/Operator dapat mengubah switch ini sesuai dokumen surat tugas resmi.

---

### FITUR 4: Kategori Hari "Jum'at (WFA)"

#### 4.1 Logika Kategori Hari
Pembaruan klasifikasi hari pada sistem kalender dan kegiatan:
1. **Hari Kerja Reguler**: Senin, Selasa, Rabu, Kamis (bukan hari libur nasional).
2. **Jum'at (WFA)**: Khusus hari Jum'at yang bukan merupakan hari libur nasional.
3. **Hari Libur Akhir Pekan (Weekend)**: Sabtu dan Minggu.
4. **Hari Libur Nasional**: Tanggal merah yang jatuh pada hari Senin s.d. Jumat.

#### 4.2 Deteksi Otomatis Sistem:
- Saat tanggal mulai kegiatan diisi di form, sistem memeriksa `Carbon::dayOfWeekIso`:
  - Jika `dayOfWeekIso == 5` (Jum'at) dan `HariLibur::isHoliday() == false`, maka atribut `is_wfa` otomatis diset `true` dan label UI menampilkan: **"Jum'at (WFA)"**.

---

### FITUR 5: Aturan Baru Algoritma Penjadwalan Otomatis (Rules & Engine)

Penambahan fitur 2, 3, dan 4 secara langsung memengaruhi mesin rekomendasi protokol dengan aturan berikut:

#### 5.1 Hard Rule Baru: WFA-to-Weekend Guard (Blokir Akhir Pekan di Minggu yang Sama)
> **Aturan Wajib:** Jika seorang protokol bertugas pada hari **WFA (Jum'at)**, maka protokol tersebut **TIDAK BOLEH DITUGASKAN** pada hari **Sabtu** atau **Minggu** pada **minggu yang sama**.

* **Tujuan**: Mencegah kelelahan beruntun. Personil yang telah mengorbankan waktu WFA untuk dinas lapangan di hari Jum'at dijamin mendapatkan hak libur akhir pekan penuh bersama keluarga.
* **Formula Validasi Hard Rule**:
  $$\text{Valid}_{\text{WFA\_Weekend}}(p, K_{\text{new}}) = 
  \begin{cases}
  \text{False}, & \text{jika } K_{\text{new}} \text{ jatuh pada Sabtu/Minggu dan } p \text{ bertugas di Jum'at minggu berjalan} \\
  \text{True}, & \text{lainnya}
  \end{cases}$$

---

#### 5.2 Hard/Rotational Rule Baru: Rotasi WFA Antar-Minggu
> **Aturan Wajib:** Jika protokol $P$ bertugas pada hari WFA (Jum'at), maka pada hari WFA minggu berikutnya protokol $P$ **TIDAK DAPAT BERTUGAS LAGI** jika masih terdapat protokol aktif lain yang **belum pernah bertugas pada hari WFA** dalam siklus bulan tersebut.

* **Siklus Reset Rotasi**: Jika **SELURUH** protokol aktif dalam tim sudah pernah bertugas di hari WFA masing-masing minimal 1 kali, maka status siklus ter-reset dan seluruh protokol kembali memenuhi syarat untuk rotasi putaran kedua.

---

#### 5.3 Target Keseimbangan Kunker (Minimal 1x Kunker per Bulan)
> **Aturan Prioritas:** Setiap protokol aktif diupayakan melaksanakan dinas Kunjungan Kerja (Kunker) **minimal 1 kali per bulan**.

* **Mekanisme Algoritma**:
  - Pada kegiatan yang berstatus `is_kunker = true`, sistem mengevaluasi riwayat $T_{\text{kunker}}$ bulan berjalan untuk setiap protokol.
  - Personil dengan $T_{\text{kunker}} = 0$ (belum pernah kunker bulan ini) memperoleh **bobot dorongan prioritas tinggi ($+40.0$ poin)** saat eliminasi Hard Rules lolos.

---

#### 5.4 Penyesuaian Scoring Keseimbangan Multidimensi (Termasuk "Kemungkinan Dibayar")
Distribusi keseimbangan tidak lagi dihitung secara sederhana, melainkan membandingkan 6 dimensi penugasan per individu terhadap rata-rata tim:

$$\text{FinalScore}(p) = W_{\text{min}} \cdot S_{\text{min}} + W_{\text{pay}} \cdot S_{\text{pay}} + W_{\text{cond}} \cdot S_{\text{cond}} + W_{\text{kunker}} \cdot S_{\text{kunker}} + W_{\text{wfa}} \cdot S_{\text{wfa}} + W_{\text{off}} \cdot S_{\text{off}} + W_{\text{idle}} \cdot S_{\text{idle}}$$

##### Tabel Bobot Multidimensi:
| Variabel | Bobot ($W$) | Formula | Penjelasan |
| :--- | :---: | :--- | :--- |
| **$S_{\text{min}}$** | **50.0** | $1 \text{ jika } T_{\text{total}} = 0 \text{ else } 0$ | Wajib minimal 1x tugas per bulan (Aturan H2). |
| **$S_{\text{kunker}}$** | **35.0** | $\max(0, \text{AvgKunker} - T_{\text{kunker}})$ | Prioritas protokol yang belum kunker bulan ini. |
| **$S_{\text{pay}}$** | **15.0** | $\max(0, \text{AvgPaid} - T_{\text{paid}})$ | Keseimbangan perolehan honorarium pasti. |
| **$S_{\text{cond}}$** | **10.0** | $\max(0, \text{AvgCond} - T_{\text{conditional}})$ | Keseimbangan perolehan peluang honorarium. |
| **$S_{\text{wfa}}$** | **15.0** | $\max(0, \text{AvgWFA} - T_{\text{wfa}})$ | Keseimbangan piket hari WFA. |
| **$S_{\text{off}}$** | **15.0** | $\max(0, \text{AvgWeekend} - T_{\text{weekend}})$ | Keseimbangan tugas akhir pekan/hari libur. |
| **$S_{\text{idle}}$** | **1.0** | $\min(30, \text{HariSejakTugasTerakhir})$ | Rotasi personil dengan istirahat terlama. |

---

### FITUR 6: Penyesuaian Format Laporan Rekapitulasi & Ekspor (Excel & PDF)

Sesuai permintaan pimpinan, format laporan rekapitulasi distribusi tugas disederhanakan dan diselaraskan pada **7 metrik spesifik**, dengan ketentuan khusus:
1. **Nomor NIP Dihapus**: Kolom NIP ditiadakan dari tampilan Web, Ekspor Excel, dan Cetak PDF agar tampilan lebih ringkas dan fokus pada nama personil.
2. **Kolom Kunker Menyertakan Rincian/List Kegiatan**: Kolom Kunker tidak hanya menampilkan jumlah total tugas kunker (misal `2x`), melainkan juga menampilkan rincian daftar kegiatan Kunker apa saja yang telah diikuti oleh protokol tersebut pada periode bulan terpilih (misal: judul kegiatan dan tanggal kunker).

```
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                        REKAPITULASI KESEIMBANGAN DISTRIBUSI TUGAS PROTOKOL                             │
├────┬────────────────────┬──────────┬──────────┬──────────────────────┬───────┬─────────┬─────────┬───────┤
│ NO │   NAMA PROTOKOL    │ TIDAK    │ DIBAYAR  │        KUNKER        │  WFA  │ HARI    │ HARI    │ TOTAL │
│    │                    │ DIBAYAR  │          │    (TOTAL & LIST)    │ (JUM) │ WEEKEND │ WEEKDAY │ TUGAS │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 1  │ Dewi Kurnia, S.AP  │    2x    │    3x    │ 2x                   │  1x   │   1x    │   4x    │  5x   │
│    │ 081234567890       │          │          │ • 12/10: Rakor PBJ   │       │         │         │       │
│    │                    │          │          │ • 24/10: Kunker DPR  │       │         │         │       │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 2  │ Andi Prasetyo, S.H │    1x    │    3x    │ 1x                   │  1x   │   2x    │   2x    │  4x   │
│    │ 081298765432       │          │          │ • 05/10: Kunker Bali │       │         │         │       │
└────┴────────────────────┴──────────┴──────────┴──────────────────────┴───────┴─────────┴─────────┴───────┘
```

#### Rincian Kolom Laporan yang Ditampilkan:
1. **Nomor & Identitas**: No, Nama Lengkap & Nomor WhatsApp (Tanpa NIP).
2. **Total Tidak Dibayar**: Akumulasi penugasan kegiatan `unpaid`.
3. **Total Dibayar**: Akumulasi penugasan kegiatan berbayar (`paid` + `conditional`).
4. **Kunker (Total & List Kegiatan)**: 
   - Menampilkan total frekuensi penugasan Kunker (cth: `2x`).
   - Disertai daftar nama kegiatan Kunker yang dijalankan personil pada bulan tersebut (beserta tanggal atau lokasi singkat).
   - Format pada Web: Badge angka total + bullet list nama agenda kunker di bawahnya.
   - Format pada Excel: Teks terstruktur dalam sel (cth: `2x (12/10: Rakor PBJ, 24/10: Kunker DPR)` atau multiline cell).
   - Format pada PDF: Angka frekuensi tebal + list bertitik halus di bawahnya.
5. **WFA**: Total penugasan pada hari Jum'at WFA (`is_wfa = true`).
6. **Hari Weekend**: Total penugasan pada hari Sabtu atau Minggu.
7. **Hari Weekday**: Total penugasan pada hari kerja reguler (Senin s.d. Jumat non-libur).
8. **Total Keseluruhan**: Total seluruh penugasan dalam bulan terpilih.

Berlaku konsisten dan sinkron pada 3 modul:
- Tampilan Web Inertia/Vue (`resources/js/Pages/Laporan/RekapDistribusi.vue`)
- Ekspor Dokumen Microsoft Excel (`app/Exports/RekapDistribusiExport.php`)
- Ekspor Dokumen Cetak Adobe PDF (`resources/views/exports/rekap-distribusi-pdf.blade.php`)

---

## 3. Perubahan Skema Database (Database Migrations)

Untuk mendukung performa tinggi pada skala data besar (hingga 200 juta baris penugasan):

```sql
-- 1. Penambahan Soft Deletes dan Flag Kunker pada Master Template
ALTER TABLE jenis_kegiatan 
    ADD COLUMN deleted_at TIMESTAMP WITH TIME ZONE NULL,
    ADD COLUMN is_kunker BOOLEAN NOT NULL DEFAULT FALSE;

-- 2. Modifikasi Tipe Pembayaran (Mendukung 'conditional')
-- (Memperbarui enum / check constraint payment_type)
ALTER TABLE jenis_kegiatan 
    DROP CONSTRAINT IF EXISTS jenis_kegiatan_payment_type_check;
ALTER TABLE jenis_kegiatan 
    ADD CONSTRAINT chk_jenis_payment_type 
    CHECK (payment_type IN ('paid', 'conditional', 'unpaid'));

-- 3. Penambahan Flag pada Tabel Transaksional Kegiatan
ALTER TABLE kegiatan 
    ADD COLUMN is_kunker BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN is_wfa BOOLEAN NOT NULL DEFAULT FALSE;

-- 4. Indeks Komposit Berkinerja Tinggi (200 Juta Baris Compliance)
CREATE INDEX idx_kegiatan_kunker_wfa ON kegiatan (is_kunker, is_wfa, start_datetime);
CREATE INDEX idx_jenis_kegiatan_deleted ON jenis_kegiatan (deleted_at);
```

---

## 4. Rencana Tahapan Implementasi (Rollout Phases)

Untuk menjaga kualitas kode, kebersihan arsitektur, dan stabilitas aplikasi, pengerjaan dibagi menjadi 4 tahap:

* **Tahap 1: Schema & Master Data Update**
  - Migrasi penambahan kolom `is_kunker`, `is_wfa`, `deleted_at`, dan tipe `conditional`.
  - Update Enum `PaymentType.php` & `Protokol` queries.
  - Implementasi fitur Hapus Master Jenis Kegiatan pada `MasterReferensi.php` dengan validasi aman.
* **Tahap 2: Form Input & UI Detection Update**
  - Form Master Jenis Kegiatan: radio 3 tipe pembayaran & switch Kunker.
  - Form Kegiatan: auto-detection hari Jum'at (WFA), badge visual tipe pembayaran, dan toggle Kunker.
* **Tahap 3: Algoritma Auto-Scheduling Engine Update**
  - Implementasi Hard Rule: *WFA-to-Weekend Guard* (blokir Sabtu/Minggu jika bertugas WFA Jum'at).
  - Implementasi *Rotasi WFA Antar-Minggu* & *Target Min 1x Kunker*.
  - Pembaruan rumus *Multidimensi Scoring* pada `DistributionScorer.php`.
* **Tahap 4: Penyesuaian Laporan Rekapitulasi & Ekspor (Excel & PDF)**
  - Update query rekapitulasi `Protokol::withMonthlyMetrics()` dan eager-load / aggregate riwayat kegiatan Kunker per protokol pada bulan terkait.
  - Penyesuaian tabel Vue 3 Inertia `resources/js/Pages/Laporan/RekapDistribusi.vue` (tanpa NIP, 7 metrik, dan daftar kegiatan kunker pada kolom Kunker).
  - Penyesuaian `RekapDistribusiExport.php` (Excel tanpa NIP, rincian list Kunker di cell Kunker) dan `resources/views/exports/rekap-distribusi-pdf.blade.php`.
  - Pembuatan automated test menyeluruh (Unit & Feature Tests).

---

## 5. Kriteria Penerimaan (Acceptance Criteria)

1. ✅ Admin dapat menghapus jenis kegiatan dengan aman (jika belum ada relasi) atau diarsipkan (jika sudah ada kegiatan terkait).
2. ✅ Tersedia 3 opsi pembayaran: Dibayar, Kemungkinan Dibayar, dan Tidak Dibayar.
3. ✅ Kegiatan dapat ditandai sebagai Kunjungan Kerja (Kunker) dan terhitung di statistik.
4. ✅ Hari Jum'at non-libur otomatis terdeteksi sebagai "Jum'at (WFA)".
5. ✅ Protokol yang bertugas di Jum'at WFA otomatis diblokir dari tugas Sabtu & Minggu pada minggu yang sama.
6. ✅ Protokol yang bertugas di Jum'at WFA tidak dapat bertugas di WFA minggu berikutnya selama masih ada personil lain yang belum pernah tugas WFA.
7. ✅ Protokol yang belum pernah Kunker bulan berjalan mendapat prioritas utama saat ada agenda Kunker.
8. ✅ Laporan rekapitulasi (Web, Excel, dan PDF) bersih dari kolom NIP, menampilkan tepat 7 metrik, serta menyajikan jumlah dan rincian agenda Kunker yang diikuti protokol.
9. ✅ Seluruh automated tests lulus 100%.
