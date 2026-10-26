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
5. **Pembaruan Format Laporan Rekapitulasi**: Disederhanakan dan difokuskan pada 7 metrik pokok kedinasan serta dilengkapi rincian agenda Kunker (tanpa NIP).
6. **Sistem Leveling Beban Kerja & Status Keseimbangan Tim**: Klasifikasi otomatis pegawai (*Overwork*, *Seimbang*, *Harus Ditugaskan*) serta evaluasi kesehatan distribusi tim secara makro berbasis *Fairness Index*.

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
│ 7. Leveling Beban & Status Tim │ • 3 Level: Overwork, Seimbang, Ditugaskan  │
│                                │ • Indikator Status Keseimbangan Tim (Gini) │
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

### FITUR 7: Sistem Leveling Beban Kerja Pegawai & Status Keseimbangan Tim

#### 7.1 Latar Belakang & Tujuan
Untuk memudahkan pimpinan dan operator dalam mengevaluasi keadilan penugasan secara cepat dan mengambil keputusan manajerial yang tepat, sistem menyediakan klasifikasi **Level Beban Kerja Individu** (*Overwork*, *Seimbang*, *Harus Ditugaskan*) serta **Status Keseimbangan Tim** secara kolektif pada setiap periode bulanan.

---

#### 7.2 Klasifikasi 3 Level Beban Kerja Individu (Per Pegawai)

Status individu ditentukan secara otomatis dengan membandingkan riwayat tugas pegawai terhadap **Rata-rata Tim ($\mu_{\text{tugas}}$)** dan kepatuhan terhadap **Hard Rules** kedinasan pada bulan berjalan:

$$\mu_{\text{tugas}} = \frac{\sum_{p \in P_{\text{active}}} T_{\text{total}, p}}{|P_{\text{active}}|}$$

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                      3 LEVEL STATUS BEBAN KERJA PROTOKOL                         │
├─────────────────────┬───────────────────┬────────────────────────────────────────┤
│ LEVEL STATUS        │ WARNA BADGE       │ INDIKATOR / KRITERIA KELAYAKAN         │
├─────────────────────┼───────────────────┼────────────────────────────────────────┤
│ 1. OVERWORK         │ 🔴 Merah (Rose)   │ Kelebihan Beban & Berisiko Kelelahan   │
│ 2. SEIMBANG         │ 🟢 Hijau (Emerald)│ Beban Proporsional & Memenuhi Target   │
│ 3. HARUS DITUGASKAN │ 🟡 Kuning (Amber) │ Kekurangan Beban / Belum Memenuhi Hak  │
└─────────────────────┴───────────────────┴────────────────────────────────────────┘
```

##### A. Level 1: OVERWORK (🔴 Merah / Rose — Kelebihan Beban / Butuh Istirahat)
Terpicu jika personil memenuhi **salah satu atau lebih** kondisi berikut:
1. **Volume Tugas Melampaui Batas**: $T_{\text{total}} \ge \mu_{\text{tugas}} + 2$ (melebihi rata-rata tim sebanyak 2 kegiatan atau lebih).
2. **Kelelahan Akhir Pekan (Over-Weekend)**: Bertugas pada hari libur/weekend $\ge 3$ kali dalam satu bulan ($T_{\text{weekend}} \ge 3$).
3. **Kelebihan Kunker**: Melakukan Kunjungan Kerja $\ge 3$ kali dalam sebulan ($T_{\text{kunker}} \ge 3$) sementara masih ada rekan lain dalam tim yang $T_{\text{kunker}} = 0$.
4. **WFA-Weekend Spike**: Bertugas pada hari Jum'at WFA dan akhir pekannya terisi kegiatan lapangan.
> **Intervensi Algoritma:** Pegawai berstatus *Overwork* otomatis **diberi penalti nilai rekomendasi / direm** oleh mesin penjadwalan agar tidak terpilih pada kegiatan baru berikutnya, memberi ruang istirahat penuh (*fatigue prevention*).

##### B. Level 3: HARUS DITUGASKAN (🟡 Kuning / Amber — Prioritas Utama Penugasan)
Terpicu jika personil memenuhi **salah satu atau lebih** kondisi kekurangan beban atau hak berikut:
1. **Nol Tugas (Prioritas Mutlak)**: Belum pernah bertugas sama sekali di bulan berjalan ($T_{\text{total}} = 0$).
2. **Volume Tugas Rendah**: $T_{\text{total}} \le \mu_{\text{tugas}} - 2$ (tertinggal jauh dari rata-rata tim).
3. **Belum Pernah Kunker**: Total Kunker bulan ini masih $0\text{x}$ ($T_{\text{kunker}} = 0$), padahal aturan mewajibkan minimal 1x Kunker per bulan.
4. **Kesenjangan Pembayaran**: Belum pernah mendapatkan kegiatan Dibayar atau Kemungkinan Dibayar ($T_{\text{paid}} + T_{\text{conditional}} = 0$), sementara rekan-rekan lain rata-rata sudah mendapatkan $\ge 2$ kegiatan berbayar.
5. **Belum Piket WFA**: Belum pernah bertugas pada hari Jum'at WFA pada siklus rotasi berjalan.
> **Intervensi Algoritma:** Pegawai berstatus *Harus Ditugaskan* otomatis mendapatkan **bobot dorongan prioritas tertinggi ($+35$ hingga $+50$ poin)** di mesin auto-scheduling sehingga langsung menempati peringkat teratas rekomendasi.

##### C. Level 2: SEIMBANG (🟢 Hijau / Emerald — Beban Ideal / Optimal)
Terpicu jika kondisi dinas pegawai berada dalam rentang sehat dan proporsional:
1. Total tugas berada dalam deviasi wajar dari rata-rata tim: $|T_{\text{total}} - \mu_{\text{tugas}}| \le 1$.
2. Target minimal bertugas tercapai ($T_{\text{total}} \ge 1$).
3. Target minimal Kunker bulanan tercapai ($T_{\text{kunker}} \ge 1$).
4. Rasio penugasan hari kerja (*weekday*) vs hari libur (*weekend*) seimbang dan tidak melanggar waktu istirahat.

---

#### 7.3 Status Keseimbangan Tim (Kolektif 1 Tim)

Untuk mengetahui kesehatan keadilan 1 tim protokol secara makro, sistem mengukur **Disparitas / Kesenjangan Beban (Fairness Gap)**:

$$\text{Fairness Gap} = \max_{p \in P_{\text{active}}}(T_{\text{total}, p}) - \min_{p \in P_{\text{active}}}(T_{\text{total}, p})$$

$$\text{Indeks Keseimbangan Tim (\%)} = 
\begin{cases} 
100\%, & \text{jika } \text{Fairness Gap} \le 1 \\
\max(60\%, 100\% - (\text{Fairness Gap} \times 10\%)), & \text{jika } \text{Fairness Gap} > 1
\end{cases}$$

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                      3 STATUS KESEHATAN KESEIMBANGAN TIM                         │
├─────────────────────┬──────────────┬─────────────────────────────────────────────┤
│ STATUS TIM          │ INDEKS (%)   │ PENJELASAN KONDISI DISTRIBUSI TIM           │
├─────────────────────┼──────────────┼─────────────────────────────────────────────┤
│ 🟢 SANGAT SEIMBANG  │ 90% – 100%   │ Beban terbagi rata (selisih max-min ≤ 1),   │
│                     │              │ tidak ada overwork, semua sudah kunker.     │
│ 🟡 CUKUP SEIMBANG   │ 75% – 89%    │ Selisih tugas 2–3 (wajar karena agenda      │
│                     │              │ kunker multi-hari), rotasi berjalan normal. │
│ 🔴 PERLU PEMERATAAN │ < 75%        │ Tim timpang! Ada personil overwork sementara│
│    (TIDAK SEIMBANG) │              │ personil lain masih 0 tugas / belum kunker. │
└─────────────────────┴──────────────┴─────────────────────────────────────────────┘
```

---

#### 7.4 Studi Kasus & Simulasi Nyata

* **Kondisi Tim**: 5 Protokol Aktif (Budi, Siti, Fauzi, Dewi, Rian).
* **Total Penugasan Berjalan**: 20 Kegiatan $\rightarrow$ **Rata-rata Tim ($\mu_{\text{tugas}}$)** = $\mathbf{4\text{ tugas per orang}}$.

```
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                        SIMULASI REKAPITULASI & LEVELING KESEIMBANGAN TIM                               │
├────┬────────────────────┬──────────┬──────────┬──────────────────────┬───────┬─────────┬─────────┬───────┤
│ NO │   NAMA PROTOKOL    │ TIDAK    │ DIBAYAR /│        KUNKER        │  WFA  │ HARI    │ HARI    │ TOTAL │
│    │                    │ DIBAYAR  │ KEMUNGK. │    (TOTAL & LIST)    │ (JUM) │ WEEKEND │ WEEKDAY │ TUGAS │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 1  │ Budi Pratama       │    2x    │    5x    │ 3x (Solo, Bali, Mdn) │  1x   │   3x    │   4x    │  7x   │
│    │ STATUS: 🔴 OVERWORK — [Beban > Rata-rata (+3), Weekend Tinggi (3x), Kunker 3x]                    │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 2  │ Siti Rahma         │    1x    │    3x    │ 1x (Surabaya)        │  1x   │   1x    │   3x    │  4x   │
│    │ STATUS: 🟢 SEIMBANG — [Beban Ideal Sesuai Rata-rata (4x), Target Kunker Terpenuhi]                │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 3  │ Ahmad Fauzi        │    2x    │    2x    │ 1x (Bandung)         │  1x   │   1x    │   3x    │  4x   │
│    │ STATUS: 🟢 SEIMBANG — [Beban Ideal Sesuai Rata-rata (4x), Target Kunker Terpenuhi]                │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 4  │ Dewi Lestari       │    3x    │    1x    │ 0x (Belum Ada)       │  1x   │   1x    │   3x    │  4x   │
│    │ STATUS: 🟡 HARUS DITUGASKAN — [⚠️ Belum Pernah Kunker (0x), Honorarium Minim (1x)]                │
├────┼────────────────────┼──────────┼──────────┼──────────────────────┼───────┼─────────┼─────────┼───────┤
│ 5  │ Rian Hidayat       │    1x    │    0x    │ 0x (Belum Ada)       │  0x   │   0x    │   1x    │  1x   │
│    │ STATUS: 🟡 HARUS DITUGASKAN — [⚠️ Jam Terbang Tertinggal (-3), Belum Kunker, Belum Honor]         │
└────┴────────────────────┴──────────┴──────────┴──────────────────────┴───────┴─────────┴─────────┴───────┘
```

##### Evaluasi Status Keseimbangan Tim Kasus di Atas:
* Tugas Terbanyak = 7x (Budi), Tugas Tersedikit = 1x (Rian) $\rightarrow$ $\text{Fairness Gap} = 7 - 1 = \mathbf{6\text{ tugas}}$.
* $\text{Indeks Keseimbangan Tim} = 100\% - (6 \times 10\%) = \mathbf{40\% \quad (\text{Skor: } 60\%)}$.
* **Banner Ringkasan Header Laporan**:
  ```
  [STATUS TIM: 🔴 PERLU PEMERATAAN (Indeks: 60%)]
  Ringkasan: 1 Overwork | 2 Seimbang | 2 Harus Ditugaskan
  Diagnosa: Beban Budi Pratama melampaui batas (+3), sementara Dewi dan Rian belum pernah Kunker.
  ```

---

#### 7.5 Tampilan Antarmuka & Output (Web, Excel, PDF)
1. **Halaman Web Inertia/Vue (`RekapDistribusi.vue`)**:
   - Header memuat **Card Status Keseimbangan Tim** dengan indikator warna, skor persentase, dan diagnosa singkat.
   - Kolom terakhir tabel memuat badge dinamis: `🔴 Overwork`, `🟢 Seimbang`, `🟡 Harus Ditugaskan` lengkap dengan tooltip alasan.
2. **Ekspor Microsoft Excel (`RekapDistribusiExport.php`)**:
   - Baris 4 memuat ringkasan Status Keseimbangan Tim dan Indeks Fairness.
   - Kolom 'Status Beban' dan 'Catatan Diagnosa' menyajikan status level tiap personil.
3. **Ekspor Cetak Adobe PDF (`rekap-distribusi-pdf.blade.php`)**:
   - Kotak ringkasan eksekutif Status Keseimbangan Tim di bawah judul dokumen.
   - Kolom status level ber-badge warna formal untuk arsip pimpinan.

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
* **Tahap 4: Penyesuaian Laporan Rekapitulasi, Leveling & Ekspor (Excel & PDF)**
  - Update query rekapitulasi `Protokol::withMonthlyMetrics()` dan eager-load / aggregate riwayat kegiatan Kunker per protokol pada bulan terkait.
  - Implementasi fungsi kalkulator Status Keseimbangan Individu (*Overwork*, *Seimbang*, *Harus Ditugaskan*) dan Status Keseimbangan Tim (Fairness Index).
  - Penyesuaian antarmuka Vue 3 Inertia `resources/js/Pages/Laporan/RekapDistribusi.vue`:
    * Tanpa kolom NIP.
    * 7 metrik pokok.
    * Kolom Kunker menampilkan total + list agenda.
    * Card Status Keseimbangan Tim di bagian atas.
    * Kolom badge Level Status Beban Kerja per pegawai.
  - Penyesuaian `RekapDistribusiExport.php` (Excel) dan `resources/views/exports/rekap-distribusi-pdf.blade.php`.
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
9. ✅ Sistem mengklasifikasikan beban kerja individu secara otomatis ke dalam 3 level: **Overwork** (merah), **Seimbang** (hijau), atau **Harus Ditugaskan** (kuning) berdasarkan perbandingan terhadap rata-rata tim dan target minimal kedinasan.
10. ✅ Sistem menghitung **Status Keseimbangan Tim** secara kolektif (Sangat Seimbang, Cukup Seimbang, atau Perlu Pemerataan) dengan visualisasi yang jelas pada Web, Excel, dan PDF.
11. ✅ Seluruh automated tests lulus 100%.
