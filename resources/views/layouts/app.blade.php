<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'SiJaProt') }} — Kemendikdasmen</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind / Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- TOPBAR / KEMENDIKDASMEN HEADER -->
    <header class="bg-[#1A5276] text-white shadow-md sticky top-0 z-40 border-b border-blue-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 rounded-lg bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center font-bold text-white shadow-inner group-hover:bg-white/20 transition">
                        <svg class="w-6 h-6 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold tracking-tight text-lg text-white">SiJaProt</span>
                            <span class="text-[11px] uppercase tracking-wider bg-yellow-400 text-blue-950 font-extrabold px-2 py-0.5 rounded-full">Kemendikdasmen</span>
                        </div>
                        <p class="text-xs text-blue-100/80 font-normal">Sistem Jadwal Protokol Terdistribusi Adil</p>
                    </div>
                </a>
            </div>

            <div class="flex items-center space-x-4">
                @auth
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-400 to-amber-200 text-blue-950 flex items-center justify-center font-bold text-xs ring-2 ring-white/30">
                            {{ substr(auth()->user()->name, 0, 2) }}
                        </div>
                        <div class="text-left hidden md:block">
                            <div class="text-xs font-semibold text-white leading-tight">{{ auth()->user()->name }}</div>
                            <div class="text-[11px] text-blue-200">{{ auth()->user()->role?->label() ?? 'Admin' }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs bg-white/10 hover:bg-white/20 text-white px-2.5 py-1.5 rounded-md transition" title="Logout">
                                Keluar
                            </button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-xs bg-white text-[#1A5276] font-bold px-3.5 py-1.5 rounded-lg shadow-sm hover:bg-blue-50 transition">
                        Masuk Sistem
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- NAVIGATION TABS -->
    <nav class="bg-white border-b border-slate-200 sticky top-16 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-1 overflow-x-auto py-2">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('dashboard') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }} transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <span>1. Dashboard Kalender</span>
                </a>
                <a href="{{ route('kegiatan.create') }}" class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('kegiatan.*') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }} transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>2. Input Kegiatan & Auto-Scheduling</span>
                </a>
                <a href="{{ route('master.protokol') }}" class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('master.protokol') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }} transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>3. Master Protokol (Pegawai)</span>
                </a>
                <a href="{{ route('master.referensi') }}" class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('master.referensi*') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }} transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span>4. Master Jenis & Libur</span>
                </a>
                <a href="{{ route('laporan.distribusi') }}" class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('laporan.*') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }} transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>5. Rekap Distribusi & Laporan</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN BODY -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full">
        @if (session()->has('success'))
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-center space-x-2">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} Kementerian Pendidikan Dasar dan Menengah RI — Sistem Jadwal Protokol (SiJaProt).
    </footer>

    @livewireScripts
</body>
</html>
