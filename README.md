# 🏛️ SiJaProt — Sistem Jadwal Protokol Kemendikdasmen RI
> **Aplikasi Penjadwalan Cerdas Berbasis Algoritma Distribusi Adil untuk Protokol Kemendikdasmen**

---

## 📌 Navigasi Dokumen Acuan Development

Paket dokumen dan prototipe ini dirancang khusus sebagai panduan arsitektur dan implementasi bagi **Senior Fullstack Engineer (Laravel 12 / Livewire v3 / PostgreSQL 17 / Redis)**:

| Dokumen / Aset | Lokasi File | Deskripsi |
| :--- | :--- | :--- |
| **Interactive Prototype (SPA)** | [`prototypes/index.html`](file:///Users/mac/Sites/freelance/sijaprot/prototypes/index.html) | Mockup interaktif lengkap (Dashboard Kalender, Auto-Scheduler, Master Data, Laporan) |
| **Product Requirements (PRD)** | [`docs/PRD.md`](file:///Users/mac/Sites/freelance/sijaprot/docs/PRD.md) | Spesifikasi kebutuhan bisnis, aturan kuncian, & scope final |
| **Engine Algoritma Penjadwalan** | [`docs/SCHEDULING_ALGORITHM.md`](file:///Users/mac/Sites/freelance/sijaprot/docs/SCHEDULING_ALGORITHM.md) | Logika matematis, bobot scoring, Hard & Soft Rules, Pseudocode PHP |
| **PostgreSQL 17 DDL Schema** | [`docs/DATABASE_SCHEMA.sql`](file:///Users/mac/Sites/freelance/sijaprot/docs/DATABASE_SCHEMA.sql) | DDL SQL lengkap (Tabel, Index, Foreign Keys, Audit Trail) |
| **Panduan Teknis Laravel 12** | [`docs/LARAVEL_DEVELOPMENT_GUIDE.md`](file:///Users/mac/Sites/freelance/sijaprot/docs/LARAVEL_DEVELOPMENT_GUIDE.md) | Panduan instalasi, struktur kelas service, Livewire components, & Redis queue |

---

## ⚡ Tech Stack Ringkas

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Database**: PostgreSQL 17
- **In-Memory Cache & Queue**: Redis 7.x
- **Frontend Framework**: Livewire v3 + Alpine.js
- **Styling**: Tailwind CSS v4
- **Asset Bundler**: Vite
- **Exporting**: `maatwebsite/excel` & `barryvdh/laravel-dompdf`
- **Audit Logging**: `spatie/laravel-activitylog`

---

## 🚀 Cara Menjalankan Prototype di Browser
Buka file [`prototypes/index.html`](file:///Users/mac/Sites/freelance/sijaprot/prototypes/index.html) langsung di browser (Chrome, Safari, Firefox, Edge) tanpa perlu web server tambahan.
