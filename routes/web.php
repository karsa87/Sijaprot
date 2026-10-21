<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LaporanExportController;
use App\Livewire\Dashboard\KalenderMonitoring;
use App\Livewire\Kegiatan\FormKegiatan;
use App\Livewire\Laporan\RekapDistribusi;
use App\Livewire\Master\DaftarProtokol;
use App\Livewire\Master\MasterReferensi;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    // SEC-04: Rate Limiting 5 percobaan per menit untuk mencegah brute-force login
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    // 1. Dashboard Kalender 1-31 Interaktif (Akses Semua Role Login)
    Route::get('/dashboard', KalenderMonitoring::class)->name('dashboard');

    // 2. Penjadwalan & Kegiatan (Akses Semua Role Login)
    Route::get('/kegiatan/baru', FormKegiatan::class)->name('kegiatan.create');
    Route::get('/kegiatan/{kegiatan}/edit', FormKegiatan::class)->name('kegiatan.edit');

    // SEC-01: Proteksi Hak Akses (BFLA Defense) Khusus Admin Protokol & Super Admin
    Route::middleware('can:manage-master')->group(function () {
        // 3. Master Data Pegawai Protokol
        Route::get('/master/protokol', DaftarProtokol::class)->name('master.protokol');

        // 4. Master Referensi (Jenis Kegiatan & Libur Nasional)
        Route::get('/master/referensi', MasterReferensi::class)->name('master.referensi');

        // 5. Rekapitulasi Distribusi & Export
        Route::get('/laporan/distribusi', RekapDistribusi::class)->name('laporan.distribusi');
        Route::get('/laporan/export/excel', [LaporanExportController::class, 'exportExcel'])->name('laporan.export.excel');
        Route::get('/laporan/export/pdf', [LaporanExportController::class, 'exportPdf'])->name('laporan.export.pdf');
    });
});
