# 🛠️ Panduan Pengembangan Laravel 12 (SiJaProt)
**Role:** Senior Fullstack Engineer Implementation Guide  
**Stack:** Laravel 12.x, PHP 8.2+, PostgreSQL 17, Redis, Livewire v3, Tailwind CSS v4, Alpine.js  
**Tanggal:** 20 Oktober 2026

---

## 1. Langkah Inisiasi & Setup Lingkungan

### 1.1 Inisialisasi Project Laravel 12
Jalankan perintah berikut di direktori workspace `/Users/mac/Sites/freelance/sijaprot`:

```bash
# 1. Buat project Laravel 12 jika belum terinstall
composer create-project laravel/laravel . "12.*"

# 2. Pasang Laravel Breeze untuk Auth Scaffolding (Blade stack)
composer require laravel/breeze --dev
php artisan breeze:install blade

# 3. Pasang Livewire v3 & Package Utama
composer require livewire/livewire
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf

# 4. Pasang Asset Frontend & Utility
npm install
npm install -D @tailwindcss/forms @tailwindcss/typography
npm install flatpickr
```

### 1.2 Konfigurasi Environment (`.env`)
Sesuaikan konfigurasi PostgreSQL 17 dan Redis:

```env
APP_NAME=SiJaProt
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost:8000

# PostgreSQL 17
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sijaprot
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis 7.x
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## 2. Struktur Modul & Livewire Components

### 2.1 Pemetaan Livewire Components
Buat komponen Livewire dengan artisan:

```bash
# Dashboard & Kalender
php artisan make:livewire Dashboard/KalenderMonitoring

# Kegiatan & Scheduling
php artisan make:livewire Kegiatan/FormKegiatan
php artisan make:livewire Kegiatan/HasilPenjadwalan
php artisan make:livewire Kegiatan/OverrideModal

# Master Data
php artisan make:livewire Master/DaftarProtokol
php artisan make:livewire Master/StatistikProtokolModal
php artisan make:livewire Master/DaftarJenisKegiatan
php artisan make:livewire Master/DaftarHariLibur

# Laporan
php artisan make:livewire Laporan/RekapDistribusi
```

### 2.2 Arsitektur Service & Domain Logic
Letakkan domain logic terpisah dari controller/livewire:

```
app/
├── Services/
│   └── Scheduling/
│       ├── SchedulingEngine.php        # Orchestrator penjadwalan
│       ├── GapRuleChecker.php          # Verifikasi Hard Rules (H1, H2, H3)
│       ├── DistributionScorer.php      # Kalkulasi Soft Rules (S1 - S4)
│       └── RelaxationPipeline.php      # Fallback ketika protokol kurang
├── Jobs/
│   └── ProcessAutoSchedulingJob.php    # Job antrian Redis async
├── Exports/
│   └── RekapDistribusiExport.php       # Maatwebsite Excel export
```

---

## 3. Eksekusi Background Queue untuk Penjadwalan
Proses penjadwalan otomatis yang membutuhkan kalkulasi beban kerja tim dijalankan melalui Redis queue worker:

```bash
# Menjalankan worker antrian
php artisan queue:work redis --queue=scheduling,default --tries=3
```

---

## 4. Checklist Pengujian (Testing Matrix)

Sebelum perilisan ke tahap staging, pastikan unit & feature test mencakup:
- [ ] **Test H1**: Penugasan berjarak < 24 jam dalam kota ditolak sistem.
- [ ] **Test H1 (Luar Kota)**: Penugasan luar kota mengunci ketersediaan hingga $H+1$.
- [ ] **Test H3 (Weekend Guard)**: Pegawai yang bertugas Sabtu/Minggu tidak boleh ditugaskan di Sabtu/Minggu berikutnya.
- [ ] **Test Min 1x**: Protokol dengan 0 penugasan dalam bulan berjalan selalu mendapat ranking teratas.
- [ ] **Test Override**: Setiap perubahan penugasan oleh Admin menghasilkan record pada `audit_override_logs`.
- [ ] **Test Export**: File `.xlsx` dan `.pdf` dapat digenerate tanpa memory leak.
