# 📘 Panduan Refaktorisasi: Migrasi Livewire ke Vue.js (SiJaProt)
**Target Lingkungan:** cPanel Shared Hosting (Gratis / Low Resource)  
**Tujuan Utama:** Menghilangkan kelambatan (*lag*), mencegah limitasi *Entry Process* (*Error 508 Resource Limit*), dan meningkatkan responsivitas antarmuka secara instan.

---

## 📌 1. Latar Belakang & Analisis Teknis

### Mengapa Livewire "Lag Parah" di cPanel Gratis?
1. **Server-Side Render Overhead:**  
   Setiap interaksi pengguna di Livewire (mengetik teks, mengubah tanggal, membuka modal) memicu request HTTP AJAX ke server. Server cPanel dipaksa me-load seluruh kernel Laravel, me-render template Blade di sisi server, dan mengirim potongan HTML kembali.
2. **Limitasi CloudLinux / LVE di Hosting Gratis:**  
   Hosting cPanel gratis biasanya menerapkan batasan ketat:
   - *Entry Processes (EP):* 10–20 koneksi serentak.
   - *CPU Limit:* 25% – 50% dari 1 core CPU.
   - *I/O Speed:* 1 MB/s – 2 MB/s.  
   Request Livewire yang beruntun langsung menghabiskan kuota *Entry Process*, menyebabkan browser membeku (*freeze*), error 508 (*Resource Limit Reached*), atau timeout 504.

### Mengapa Vue.js Menjadi Solusi Terbaik?
1. **Client-Side Rendering (CSR):**  
   Seluruh kalkulasi tampilan, buka-tutup modal, tab navigasi, validasi visual, dan reaktivitas formulir dieksekusi **100% di browser laptop/HP pengguna**.
2. **Beban Server Minimal:**  
   Server cPanel hanya bertugas mengirim data JSON mentah saat awal halaman dimuat atau saat form disimpan. Penggunaan CPU server turun hingga **85%**.
3. **Aset Statis Terkompilasi:**  
   Proses *compile* Vue dilakukan sekali di komputer lokal (`npm run build`). Server cPanel hanya menyajikan file statis `.js` dan `.css` yang sangat cepat.

---

## 🏗️ 2. Rekomendasi Arsitektur: **Inertia.js + Vue 3**

Daripada membangun arsitektur REST API + JWT yang rumit dan terpisah (*decoupled SPA*), arsitektur standar industri Laravel untuk Vue adalah **Inertia.js**:

* **Tetap menggunakan ekosistem Laravel:**  
  Routing tetap di `routes/web.php`, autentikasi tetap session bawaan, otorisasi Gate/Role (`can:manage-master`) tetap aktif, dan validasi form tetap menggunakan Laravel Form Request.
* **Controller mengembalikan halaman Vue:**  
  Alih-alih `return view('dashboard', $data);`, controller cukup memanggil `return Inertia::render('Dashboard/Index', $data);`.
* **Zero Boilerplate API:**  
  Tidak perlu membuat controller API terpisah atau token manajemen Sanctum.

---

## 🗺️ 3. Peta Komponen yang Perlu Direfaktorisasi

Berikut adalah daftar lengkap komponen Livewire yang akan digantikan oleh Controller Laravel dan Komponen Vue 3:

| Fitur / Halaman | Komponen Livewire Lama (Dihapus) | Controller Laravel Baru | Komponen Vue 3 Baru (`resources/js/Pages/`) |
| :--- | :--- | :--- | :--- |
| **1. Dashboard Kalender** | `app/Livewire/Dashboard/KalenderMonitoring.php`<br>`resources/views/livewire/dashboard/kalender-monitoring.blade.php` | `app/Http/Controllers/DashboardController.php` | `Pages/Dashboard/KalenderMonitoring.vue`<br>`Components/CalendarGrid.vue`<br>`Components/KegiatanDetailModal.vue` |
| **2. Form Penjadwalan & Simulasi** | `app/Livewire/Kegiatan/FormKegiatan.php`<br>`resources/views/livewire/kegiatan/form-kegiatan.blade.php` | `app/Http/Controllers/KegiatanController.php` | `Pages/Kegiatan/FormKegiatan.vue`<br>`Components/SimulationResults.vue`<br>`Components/SwapProtocolModal.vue`<br>`Components/AddManualModal.vue` |
| **3. Master Pegawai Protokol** | `app/Livewire/Master/DaftarProtokol.php`<br>`resources/views/livewire/master/daftar-protokol.blade.php` | `app/Http/Controllers/Master/ProtokolController.php` | `Pages/Master/DaftarProtokol.vue`<br>`Components/ProtokolModal.vue`<br>`Components/ProtokolStatsModal.vue` |
| **4. Master Jenis & Hari Libur** | `app/Livewire/Master/MasterReferensi.php`<br>`resources/views/livewire/master/master-referensi.blade.php` | `app/Http/Controllers/Master/ReferensiController.php` | `Pages/Master/MasterReferensi.vue`<br>`Components/JenisKegiatanModal.vue`<br>`Components/HariLiburModal.vue` |
| **5. Rekap Distribusi & Laporan** | `app/Livewire/Laporan/RekapDistribusi.php`<br>`resources/views/livewire/laporan/rekap-distribusi.blade.php` | `app/Http/Controllers/LaporanDistribusiController.php` | `Pages/Laporan/RekapDistribusi.vue` |
| **Layout Aplikasi** | `resources/views/layouts/app.blade.php` | `resources/views/app.blade.php` *(Inertia Root Shell)* | `Layouts/AppLayout.vue`<br>`Components/Navbar.vue` |

---

## 🔍 4. Rincian Perubahan per Modul

---

### A. Konfigurasi Fondasi & Build Pipeline
* **Composer (`composer.json`):**
  - Hapus package: `livewire/livewire`
  - Tambah package: `inertiajs/inertia-laravel`
* **NPM (`package.json`):**
  - Tambah dependencies: `vue@^3.5`, `@inertiajs/vue3`, `@vitejs/plugin-vue`
* **Vite (`vite.config.js`):**
  - Aktifkan plugin `vue()`:
    ```javascript
    import { defineConfig } from 'vite';
    import laravel from 'laravel-vite-plugin';
    import vue from '@vitejs/plugin-vue';
    import tailwindcss from '@tailwindcss/vite';

    export default defineConfig({
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            vue(),
            tailwindcss(),
        ],
    });
    ```
* **Entry Point (`resources/js/app.js`):**
  - Setup inisialisasi Inertia dan Vue App.

---

### B. Modul 1: Dashboard Kalender Monitoring
* **Akar Masalah Livewire:**  
  Setiap klik tanggal 1–31 dan pergantian bulan me-render ulang seluruh grid kalender melalui request server.
* **Perubahan ke Vue.js:**
  - `DashboardController@index` mengirimkan data awal bulan yang sedang aktif (daftar kegiatan dalam rentang bulan tersebut).
  - Navigasi tanggal (1–31) dan filter tampilan diproses **instan secara lokal di Vue reactive state** tanpa request jaringan.
  - Pergantian bulan memanggil `router.get('/dashboard', { month, year }, { preserveState: true })`.
  - Modal detail kegiatan dan konfirmasi hapus dikontrol penuh oleh variabel reaktif `const showModal = ref(false)`.

---

### C. Modul 2: Form Input Kegiatan & Simulasi Auto-Scheduling
* **Akar Masalah Livewire:**  
  Perubahan jam/tanggal memicu event `updateAutoDetection()` via network. Simulasi rekomendasi dan modal swap rentan *lag* dan sering bentrok *lifecycle hook*.
* **Perubahan ke Vue.js:**
  - **Deteksi Otomatis di Klien:**  
    Logika deteksi weekend dan multi-day (Jumat–Minggu) langsung dihitung di Vue menggunakan *computed property*. Tidak ada jeda ketikan.
  - **Simulasi Algoritma (Tetap Aman di Backend):**  
    Saat tombol "Jalankan Simulasi Algoritma" diklik, Vue mengirim request POST via Axios/Inertia ke endpoint `POST /kegiatan/simulasi`. Controller mengeksekusi `SchedulingEngine` dan mengembalikan JSON rekomendasi.
  - **Modal Swap & Tambah Manual:**  
    Modal dibuka instan tanpa membebani server. Validasi hard rules (H1, H2, H3) dicek melalui endpoint validasi atau langsung saat konfirmasi simpan.
  - **Integritas Aturan Bisnis Tetap Utuh:**  
    Pencegahan jam minus (BUG-03), anti double submit (BUG-04), dan multi-day weekend (BUG-05) tetap berjalan optimal.

---

### D. Modul 3: Master Protokol & Master Referensi
* **Akar Masalah Livewire:**  
  Fitur pencarian (*search with debounce*) dan filter status mengirim request PHP setiap 300ms, memicu limit koneksi cPanel.
* **Perubahan ke Vue.js:**
  - Tabel master protokol menggunakan komponen Vue dengan *client-side search & filter* atau *Inertia query params*.
  - Modal form tambah/edit protokol, input validasi regex WhatsApp (`BUG-08`), batas fee (`BUG-07`), dan modal statistik muncul seketika (*instant zero latency*).
  - Tombol aksi (toggle status aktif/nonaktif, delete) menggunakan form submit Inertia yang dilengkapi status loading otomatis.

---

### E. Modul 4: Rekap Distribusi & Laporan
* **Perubahan ke Vue.js:**
  - Halaman menampilkan tabel metrik beban kerja pegawai protokol.
  - Penggantian filter bulan dan tahun memuat data via Inertia visit.
  - Tombol Ekspor Excel dan PDF tetap mengarah langsung ke route controller yang sudah ada (`LaporanExportController`).

---

### F. Routing (`routes/web.php`)
Route yang sebelumnya mengarah langsung ke kelas Livewire diubah ke Controller standar:

```php
// SEBELUMNYA (Livewire):
Route::get('/dashboard', KalenderMonitoring::class)->name('dashboard');
Route::get('/kegiatan/baru', FormKegiatan::class)->name('kegiatan.create');
Route::get('/master/protokol', DaftarProtokol::class)->name('master.protokol');

// SESUDAHNYA (Inertia + Vue):
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/kegiatan/baru', [KegiatanController::class, 'create'])->name('kegiatan.create');
Route::post('/kegiatan/simulasi', [KegiatanController::class, 'simulate'])->name('kegiatan.simulate');
Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('kegiatan.store');

Route::middleware('can:manage-master')->group(function () {
    Route::get('/master/protokol', [ProtokolController::class, 'index'])->name('master.protokol');
    Route::post('/master/protokol', [ProtokolController::class, 'store'])->name('master.protokol.store');
    Route::get('/master/referensi', [ReferensiController::class, 'index'])->name('master.referensi');
    Route::get('/laporan/distribusi', [LaporanDistribusiController::class, 'index'])->name('laporan.distribusi');
});
```

---

## 🔒 5. Jaminan Keamanan & Integritas Algoritma

1. **Algoritma Tetap di Sisi Server (Backend):**  
   Mesin algoritma inti (`SchedulingEngine.php`, `GapRuleChecker.php`, `DistributionScorer.php`) **tidak dipindahkan ke Vue/klien**. Algoritma tetap berada di PHP demi keamanan dan kerahasiaan logika penilaian.
2. **Keamanan RBAC & Audit Trail Tetap Terjaga:**  
   - Proteksi Gate `manage-master` (SEC-01) tetap diuji di route middleware dan controller.
   - Soft Deletes dan jejak audit permanen `audit_override_logs` (SEC-02) tetap berjalan di model Eloquent.
   - Concurrency Lock `Cache::lock` (SEC-03) tetap mengamankan transaksi penjadwalan.

---

## ⏳ 6. Desain UX Loading & State Transitions (Anti-Hang & Responsif)

Untuk mencegah pengguna mengira aplikasi *hang* atau *freeze* (terutama saat koneksi internet melambat atau server cPanel sedang merespons), sistem menerapkan strategi loading 3-lapis:

### A. Indikator Global Perpindahan Halaman (Page Navigation)
1. **Inertia Progress Bar (Instan 0ms Delay):**
   - Diaktifkan langsung di `resources/js/app.js` dengan opsi `delay: 0` agar garis progress di puncak layar langsung bergerak begitu tombol/menu diklik tanpa jeda.
   ```javascript
   createInertiaApp({
       progress: {
           delay: 0,
           color: '#2563EB', // Primary Blue Kemendikdasmen
           includeCSS: true,
           showSpinner: true,
       },
       // ...
   });
   ```
2. **Global Loading Bar / Spinner di Navbar (`AppLayout.vue`):**
   - Mendengarkan event global router Inertia:
     - `router.on('start')` -> aktifkan state `isNavigating = true`.
     - `router.on('finish')` -> nonaktifkan state `isNavigating = false`.
   - Menampilkan badge animasi berdenyut/spinner halus di pojok kanan atas navbar: *"Memuat halaman..."*.

### B. Indikator Tombol Aksi & Formulir (`form.processing`)
1. **Tombol Disabled Otomatis:**
   - Semua tombol form (`Simpan`, `Perbarui`, `Hapus`, `Simulasi`) terikat ke atribut `:disabled="form.processing"`.
   - Mencegah klik ganda (*multi-click*) yang dapat memicu duplikasi data atau deadlock.
2. **Animasi Spinner SVG Interaktif:**
   - Saat request berjalan, ikon tombol berganti otomatis menjadi spinner SVG berputar dengan teks status dinamis (misal: `"Menyimpan data..."`, `"Memproses..."`).

### C. Indikator Proses Berat & Algoritma (Simulasi Jadwal & Swap)
1. **Modal / Overlay Spinner Transparan:**
   - Saat menjalankan algoritma simulasi penjadwalan (`POST /kegiatan/simulasi`) atau swap protokol, area hasil menampilkan *skeleton placeholder* atau loading overlay dengan pesan jelas:
     > *"Sedang menganalisis 5 parameter algoritma & ketersediaan protokol..."*
2. **Toast Feedback Pasca-Proses:**
   - Setiap operasi selesai (sukses atau gagal), muncul notifikasi toast mengambang (*flash message*) memberi tahu status akhir secara tegas (misal: *"Jadwal berhasil disimpan"*, *"Protokol berhasil dialihkan"*).

---

## 🚀 7. Workflow Khusus Deployment cPanel Gratis

Karena hosting gratis cPanel tidak memiliki akses CLI Node.js/NPM untuk menjalankan build:

```text
[Laptop / Komputer Lokal]
1. Modifikasi file .vue
2. Jalankan perintah: npm run build
3. Folder public/build/ ter-update
       │
       ▼ (Upload via Git / FTP / File Manager)
[cPanel Hosting Gratis]
4. Hanya menerima file statis di public/build/
5. cPanel menyajikan file JS/CSS tanpa beban CPU!
```

---

## 📋 8. Rencana Tahapan Eksekusi Migrasi

1. **Tahap 1:** Instalasi dependensi Inertia.js & Vue 3, konfigurasi `vite.config.js`, dan pembuatan shell layout utama (`resources/views/app.blade.php` & `AppLayout.vue`) lengkap dengan sistem global loading progress dan spinner.
2. **Tahap 2:** Migrasi Master Data (`DaftarProtokol.vue` & `MasterReferensi.vue`) beserta controllernya, dilengkapi indikator loading pada tombol simpan dan modal.
3. **Tahap 3:** Migrasi Dashboard Kalender Interaktif (`KalenderMonitoring.vue`) dan Rekap Laporan, dengan loading skeleton saat navigasi bulan.
4. **Tahap 4:** Migrasi Form Input Kegiatan & Simulasi Algoritma (`FormKegiatan.vue`), dilengkapi overlay loading simulasi yang informatif.
5. **Tahap 5:** Pembersihan dependensi Livewire, eksekusi build produksi (`npm run build`), dan pengujian menyeluruh (*automated testing*).
