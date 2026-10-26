<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isNavigating = ref(false);

let removeStartListener = null;
let removeFinishListener = null;

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isNavigating.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isNavigating.value = false;
    });
});

onUnmounted(() => {
    if (removeStartListener) removeStartListener();
    if (removeFinishListener) removeFinishListener();
});

const user = computed(() => page.props.auth?.user);
const canManageMaster = computed(() => page.props.can?.manage_master ?? false);

const initials = computed(() => {
    if (!user.value?.name) return 'PR';
    const parts = user.value.name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return user.value.name.substring(0, 2).toUpperCase();
});

const isRouteActive = (pattern) => {
    const currentUrl = page.url;
    if (pattern === '/dashboard') {
        return currentUrl === '/dashboard' || currentUrl === '/';
    }
    return currentUrl.startsWith(pattern);
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col font-sans">
        <!-- TOPBAR / KEMENDIKDASMEN HEADER -->
        <header class="bg-[#1A5276] text-white shadow-md sticky top-0 z-40 border-b border-blue-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- LOGO & BRAND -->
                <div class="flex items-center space-x-3">
                    <Link href="/dashboard" class="flex items-center space-x-3 group">
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
                    </Link>
                </div>

                <!-- RIGHT NAV / USER & LOADING STATE -->
                <div class="flex items-center space-x-4">
                    <!-- LIVE LOADING INDICATOR (ANTI-HANG) -->
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isNavigating"
                            class="hidden sm:flex items-center space-x-2 bg-yellow-400/90 text-blue-950 text-xs px-3 py-1 rounded-full font-bold shadow-md animate-pulse"
                        >
                            <svg class="animate-spin h-3.5 w-3.5 text-blue-950" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span>Memuat data...</span>
                        </div>
                    </Transition>

                    <!-- USER INFO & LOGOUT -->
                    <div v-if="user" class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-400 to-amber-200 text-blue-950 flex items-center justify-center font-bold text-xs ring-2 ring-white/30 shadow-xs">
                            {{ initials }}
                        </div>
                        <div class="text-left hidden md:block">
                            <div class="text-xs font-semibold text-white leading-tight">{{ user.name }}</div>
                            <div class="text-[11px] text-blue-200">{{ user.role_label || 'Pegawai' }}</div>
                        </div>
                        <button
                            type="button"
                            @click="logout"
                            class="text-xs bg-white/10 hover:bg-white/20 active:bg-white/30 text-white px-2.5 py-1.5 rounded-md transition shadow-2xs font-medium cursor-pointer"
                            title="Logout dari sistem"
                        >
                            Keluar
                        </button>
                    </div>
                    <div v-else>
                        <Link
                            href="/login"
                            class="text-xs bg-white text-[#1A5276] font-bold px-3.5 py-1.5 rounded-lg shadow-sm hover:bg-blue-50 transition"
                        >
                            Masuk Sistem
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- NAVIGATION TABS -->
        <nav class="bg-white border-b border-slate-200 sticky top-16 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex space-x-1 overflow-x-auto py-2">
                    <!-- Tab 1: Dashboard -->
                    <Link
                        href="/dashboard"
                        class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium transition shrink-0"
                        :class="isRouteActive('/dashboard') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span>1. Dashboard Kalender</span>
                    </Link>

                    <!-- Tab 2: Input Kegiatan & Auto-Scheduling -->
                    <Link
                        href="/kegiatan/baru"
                        class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium transition shrink-0"
                        :class="isRouteActive('/kegiatan') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <span>2. Input Kegiatan & Auto-Scheduling</span>
                    </Link>

                    <!-- Tab 3: Master Protokol (RBAC Protected) -->
                    <Link
                        v-if="canManageMaster"
                        href="/master/protokol"
                        class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium transition shrink-0"
                        :class="isRouteActive('/master/protokol') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span>3. Master Protokol (Pegawai)</span>
                    </Link>

                    <!-- Tab 4: Master Jenis & Libur (RBAC Protected) -->
                    <Link
                        v-if="canManageMaster"
                        href="/master/referensi"
                        class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium transition shrink-0"
                        :class="isRouteActive('/master/referensi') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <span>4. Master Jenis & Libur</span>
                    </Link>

                    <!-- Tab 5: Rekap Distribusi & Laporan (RBAC Protected) -->
                    <Link
                        v-if="canManageMaster"
                        href="/laporan/distribusi"
                        class="inline-flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-medium transition shrink-0"
                        :class="isRouteActive('/laporan') ? 'text-blue-900 bg-blue-50 border border-blue-200 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>5. Rekap Distribusi & Laporan</span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- MAIN BODY CONTENT -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full">
            <slot />
        </main>

        <!-- TOAST NOTIFICATIONS -->
        <Toast />

        <!-- FOOTER -->
        <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
            &copy; {{ new Date().getFullYear() }} Kementerian Pendidikan Dasar dan Menengah RI — Sistem Jadwal Protokol (SiJaProt).
        </footer>
    </div>
</template>
