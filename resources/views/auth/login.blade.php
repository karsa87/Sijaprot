<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — SiJaProt Kemendikdasmen</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4 antialiased">
    <div class="max-w-md w-full">
        <!-- Logo and Brand -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 bg-[#1A5276] text-white rounded-2xl mx-auto flex items-center justify-center shadow-lg shadow-blue-900/20 mb-3">
                <svg class="w-8 h-8 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">SiJaProt</h1>
            <p class="text-xs text-slate-500 font-medium mt-1">Sistem Jadwal Protokol — Kemendikdasmen RI</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xl p-8">
            <h2 class="text-base font-bold text-slate-900 mb-1">Masuk ke Akun Anda</h2>
            <p class="text-xs text-slate-500 mb-6">Gunakan email kedinasan untuk mengakses sistem.</p>

            @if ($errors->any())
                <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Email Kedinasan</label>
                    <input id="email" type="email" name="email" value="{{ old('email', 'admin@kemdikbud.go.id') }}" required autofocus
                           class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#1A5276] focus:border-[#1A5276] outline-none transition">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="password" class="block text-xs font-semibold text-slate-700">Kata Sandi</label>
                    </div>
                    <input id="password" type="password" name="password" value="password" required
                           class="w-full px-3.5 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-[#1A5276] focus:border-[#1A5276] outline-none transition">
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center space-x-2 text-slate-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-[#1A5276] focus:ring-[#1A5276]">
                        <span>Ingat saya</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full py-2.5 px-4 bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-bold rounded-xl shadow-md transition active:scale-[0.99] flex items-center justify-center space-x-2">
                    <span>Masuk ke Dashboard</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-100 text-center">
                <span class="text-[11px] text-slate-400">Akun Default: <code>admin@kemdikbud.go.id</code> / <code>password</code></span>
            </div>
        </div>
    </div>
</body>
</html>
