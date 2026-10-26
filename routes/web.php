<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\LaporanDistribusiController;
use App\Http\Controllers\LaporanExportController;
use App\Http\Controllers\Master\ProtokolController;
use App\Http\Controllers\Master\ReferensiController;
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Penjadwalan & Kegiatan (Akses Semua Role Login)
    Route::get('/kegiatan/baru', [KegiatanController::class, 'create'])->name('kegiatan.create');
    Route::post('/kegiatan/simulasi', [KegiatanController::class, 'simulate'])->name('kegiatan.simulate');
    Route::post('/kegiatan/validate-overlap', [KegiatanController::class, 'validateOverlap'])->name('kegiatan.validate-overlap');
    Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('kegiatan.store');
    Route::get('/kegiatan/{kegiatan}/edit', [KegiatanController::class, 'edit'])->name('kegiatan.edit');
    Route::put('/kegiatan/{kegiatan}', [KegiatanController::class, 'update'])->name('kegiatan.update');
    Route::delete('/kegiatan/{kegiatan}', [KegiatanController::class, 'destroy'])->name('kegiatan.destroy');

    // SEC-01: Proteksi Hak Akses (BFLA Defense) Khusus Admin Protokol & Super Admin
    Route::middleware('can:manage-master')->group(function () {
        // 3. Master Data Pegawai Protokol
        Route::get('/master/protokol', [ProtokolController::class, 'index'])->name('master.protokol');
        Route::post('/master/protokol', [ProtokolController::class, 'store'])->name('master.protokol.store');
        Route::put('/master/protokol/{protokol}', [ProtokolController::class, 'update'])->name('master.protokol.update');
        Route::delete('/master/protokol/{protokol}', [ProtokolController::class, 'destroy'])->name('master.protokol.destroy');
        Route::patch('/master/protokol/{protokol}/toggle-status', [ProtokolController::class, 'toggleStatus'])->name('master.protokol.toggle-status');
        Route::get('/master/protokol/{protokol}/stats', [ProtokolController::class, 'stats'])->name('master.protokol.stats');

        // 4. Master Referensi (Jenis Kegiatan & Libur Nasional)
        Route::get('/master/referensi', [ReferensiController::class, 'index'])->name('master.referensi');
        Route::post('/master/referensi/jenis', [ReferensiController::class, 'storeJenis'])->name('master.referensi.jenis.store');
        Route::put('/master/referensi/jenis/{jenisKegiatan}', [ReferensiController::class, 'updateJenis'])->name('master.referensi.jenis.update');
        Route::delete('/master/referensi/jenis/{jenisKegiatan}', [ReferensiController::class, 'destroyJenis'])->name('master.referensi.jenis.destroy');
        Route::post('/master/referensi/libur', [ReferensiController::class, 'storeLibur'])->name('master.referensi.libur.store');
        Route::put('/master/referensi/libur/{hariLibur}', [ReferensiController::class, 'updateLibur'])->name('master.referensi.libur.update');
        Route::delete('/master/referensi/libur/{hariLibur}', [ReferensiController::class, 'destroyLibur'])->name('master.referensi.libur.destroy');

        // 5. Rekapitulasi Distribusi & Export
        Route::get('/laporan/distribusi', [LaporanDistribusiController::class, 'index'])->name('laporan.distribusi');
        Route::get('/laporan/export/excel', [LaporanExportController::class, 'exportExcel'])->name('laporan.export.excel');
        Route::get('/laporan/export/pdf', [LaporanExportController::class, 'exportPdf'])->name('laporan.export.pdf');
    });
});
