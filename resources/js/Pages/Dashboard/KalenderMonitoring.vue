<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    kegiatans: {
        type: Array,
        required: true,
    },
    holidays: {
        type: Array,
        required: true,
    },
    month: {
        type: Number,
        required: true,
    },
    year: {
        type: Number,
        required: true,
    },
    initialDay: {
        type: Number,
        default: 1,
    },
    monthName: {
        type: String,
        required: true,
    },
    daysInMonth: {
        type: Number,
        required: true,
    },
    startOffset: {
        type: Number,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
});

// Client-Side Day Selection (100% Instant, Zero Latency)
const selectedDay = ref(props.initialDay);

// Map Events by Day (1 to 31)
const eventsByDay = computed(() => {
    const map = {};
    for (const k of props.kegiatans) {
        if (!k.start_datetime) continue;
        const d = new Date(k.start_datetime);
        const dayNum = d.getDate();
        if (!map[dayNum]) map[dayNum] = [];
        map[dayNum].push(k);
    }
    return map;
});

// Map Holidays by Day
const holidaysByDay = computed(() => {
    const map = {};
    for (const h of props.holidays) {
        if (!h.tanggal) continue;
        const d = new Date(h.tanggal);
        const dayNum = d.getDate();
        map[dayNum] = h;
    }
    return map;
});

// Selected Day Details
const selectedEvents = computed(() => eventsByDay.value[selectedDay.value] || []);
const selectedHoliday = computed(() => holidaysByDay.value[selectedDay.value]);

const isSelectedWeekend = computed(() => {
    const dayOfWeekIndex = (props.startOffset + selectedDay.value - 1) % 7;
    return dayOfWeekIndex === 5 || dayOfWeekIndex === 6; // 5 = Sabtu, 6 = Minggu
});

const selectedDateFormatted = computed(() => {
    const dateObj = new Date(props.year, props.month - 1, selectedDay.value);
    return dateObj.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

// Navigation Month Actions
const isCurrentMonth = computed(() => {
    const now = new Date();
    return props.month === now.getMonth() + 1 && props.year === now.getFullYear();
});

const prevMonth = () => {
    const prevM = props.month === 1 ? 12 : props.month - 1;
    const prevY = props.month === 1 ? props.year - 1 : props.year;
    router.get('/dashboard', { month: prevM, year: prevY, day: 1 }, { preserveState: false, preserveScroll: true });
};

const nextMonth = () => {
    const nextM = props.month === 12 ? 1 : props.month + 1;
    const nextY = props.month === 12 ? props.year + 1 : props.year;
    router.get('/dashboard', { month: nextM, year: nextY, day: 1 }, { preserveState: false, preserveScroll: true });
};

const goToCurrentMonth = () => {
    router.get('/dashboard', {}, { preserveState: false, preserveScroll: true });
};

// Schedule on selected date
const scheduleOnSelectedDate = () => {
    const mm = String(props.month).padStart(2, '0');
    const dd = String(selectedDay.value).padStart(2, '0');
    router.visit(`/kegiatan/baru?date=${props.year}-${mm}-${dd}`);
};

// Delete Kegiatan Modal State
const showDeleteModal = ref(false);
const deletingKegiatan = ref(null);
const deleteForm = useForm({});

const confirmDeleteKegiatan = (k) => {
    deletingKegiatan.value = k;
    showDeleteModal.value = true;
};

const deleteKegiatan = () => {
    if (!deletingKegiatan.value) return;
    deleteForm.delete(`/kegiatan/${deletingKegiatan.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingKegiatan.value = null;
        },
    });
};

const formatTime = (isoString) => {
    if (!isoString) return '--:--';
    const d = new Date(isoString);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <Head title="Dashboard Kalender Monitoring" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Top Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1 -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Kegiatan Bulan Ini</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">
                            {{ stats.totalKegiatan }} <span class="text-xs font-normal text-slate-500">kegiatan</span>
                        </h3>
                        <p class="text-[11px] text-emerald-600 mt-1 flex items-center font-medium">
                            <svg class="w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Terjadwal di Sistem
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-[#1A5276] rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Protokol Siap Tugas</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">
                            {{ stats.totalProtokolAktif }} <span class="text-xs font-normal text-slate-500">/ {{ stats.totalProtokol }} pegawai</span>
                        </h3>
                        <p class="text-[11px] text-blue-600 mt-1">Status pool aktif ✓</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Indeks Keseimbangan</p>
                        <h3 class="text-2xl font-bold text-emerald-600 mt-1">
                            {{ stats.indeksKeseimbangan }}% <span class="text-xs font-normal text-slate-500">Optimal</span>
                        </h3>
                        <p class="text-[11px] text-slate-500 mt-1">Distribusi tugas adil & seimbang</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Hak Libur Terlindungi</p>
                        <h3 class="text-2xl font-bold text-slate-900 mt-1">100%</h3>
                        <p class="text-[11px] text-slate-500 mt-1">Aturan H3 Full Weekend Aktif</p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Main Calendar & Detail Split -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- LEFT 8 COLS: KALENDER INTERAKTIF 1-31 -->
                <div class="lg:col-span-8 bg-white rounded-xl border border-slate-200 shadow-xs p-5">
                    <!-- Header Bulan & Navigasi -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 gap-3">
                        <div>
                            <div class="flex items-center space-x-2">
                                <h2 class="text-lg font-bold text-slate-900">{{ monthName }} {{ year }}</h2>
                                <span v-if="isCurrentMonth" class="text-xs font-medium bg-blue-100 text-[#1A5276] px-2 py-0.5 rounded-md">
                                    Bulan Berjalan
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Klik tanggal untuk melihat detail penugasan protokol secara instan.
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200 text-xs font-semibold">
                                <button
                                    type="button"
                                    @click="prevMonth"
                                    class="px-2.5 py-1 text-slate-600 hover:text-slate-900 rounded hover:bg-white transition cursor-pointer"
                                >
                                    &lt; Prev
                                </button>
                                <button
                                    type="button"
                                    @click="goToCurrentMonth"
                                    class="px-2.5 py-1 text-blue-900 bg-white shadow-2xs rounded cursor-pointer"
                                >
                                    Bulan Ini
                                </button>
                                <button
                                    type="button"
                                    @click="nextMonth"
                                    class="px-2.5 py-1 text-slate-600 hover:text-slate-900 rounded hover:bg-white transition cursor-pointer"
                                >
                                    Next &gt;
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Days of Week Header -->
                    <div class="grid grid-cols-7 gap-1 mt-4 text-center font-semibold text-xs text-slate-600">
                        <div class="py-2">Sen</div>
                        <div class="py-2">Sel</div>
                        <div class="py-2">Rab</div>
                        <div class="py-2">Kam</div>
                        <div class="py-2">Jum</div>
                        <div class="py-2 text-rose-600 bg-rose-50/50 rounded">Sab</div>
                        <div class="py-2 text-rose-600 bg-rose-50/50 rounded">Min</div>
                    </div>

                    <!-- Grid 1-31 Cells -->
                    <div class="grid grid-cols-7 gap-1.5 mt-2">
                        <!-- Leading Empty Cells for Offset -->
                        <div
                            v-for="offset in startOffset"
                            :key="'empty-' + offset"
                            class="h-20 bg-slate-50/40 rounded-lg border border-slate-100 p-1 opacity-30"
                        ></div>

                        <!-- Days of Month -->
                        <div
                            v-for="day in daysInMonth"
                            :key="'day-' + day"
                            @click="selectedDay = day"
                            class="h-20 rounded-lg p-1.5 cursor-pointer transition border text-left flex flex-col justify-between relative"
                            :class="[
                                day === selectedDay
                                    ? 'ring-2 ring-[#1A5276] bg-blue-50/70 border-blue-400 font-bold shadow-xs'
                                    : (((startOffset + day - 1) % 7 === 5 || (startOffset + day - 1) % 7 === 6 || holidaysByDay[day])
                                        ? 'bg-rose-50/30 border-rose-100 hover:bg-rose-50/70'
                                        : 'bg-white border-slate-200 hover:bg-slate-50')
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-semibold"
                                    :class="[
                                        ((startOffset + day - 1) % 7 === 5 || (startOffset + day - 1) % 7 === 6 || holidaysByDay[day]) ? 'text-rose-600' : 'text-slate-800',
                                        day === selectedDay ? 'w-5 h-5 rounded-full bg-[#1A5276] text-white flex items-center justify-center font-bold text-[11px]' : ''
                                    ]"
                                >
                                    {{ day }}
                                </span>

                                <span
                                    v-if="holidaysByDay[day]"
                                    class="text-[8px] bg-rose-500 text-white px-1 py-0.5 rounded font-bold uppercase tracking-tight truncate max-w-[45px]"
                                    :title="holidaysByDay[day].name"
                                >
                                    LIBUR
                                </span>
                            </div>

                            <!-- Events Preview -->
                            <div v-if="eventsByDay[day] && eventsByDay[day].length > 0" class="space-y-0.5 mt-0.5 overflow-hidden">
                                <div
                                    v-for="(ev, idx) in eventsByDay[day].slice(0, 2)"
                                    :key="idx"
                                    class="text-[9px] truncate px-1 py-0.5 rounded font-medium"
                                    :class="[
                                        (typeof ev.jenis_kegiatan?.payment_type === 'object' ? ev.jenis_kegiatan?.payment_type.value : ev.jenis_kegiatan?.payment_type) === 'unpaid'
                                            ? 'bg-slate-200 text-slate-700'
                                            : ((typeof ev.location_type === 'object' ? ev.location_type.value : ev.location_type) === 'luar_kota'
                                                ? 'bg-purple-600 text-white'
                                                : (ev.is_weekend || ev.is_holiday ? 'bg-amber-500 text-white' : 'bg-emerald-600 text-white'))
                                    ]"
                                    :title="ev.title"
                                >
                                    {{ ev.title }}
                                </div>
                                <div v-if="eventsByDay[day].length > 2" class="text-[9px] text-slate-400 font-normal">
                                    +{{ eventsByDay[day].length - 2 }} lainnya
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Legend Bar -->
                    <div class="flex flex-wrap items-center gap-4 mt-5 pt-3 border-t border-slate-100 text-xs text-slate-600">
                        <div class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-emerald-600"></span><span>Dibayar (Kerja)</span></div>
                        <div class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span><span>Dibayar (Weekend/Libur)</span></div>
                        <div class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-purple-600"></span><span>Luar Kota</span></div>
                        <div class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-slate-300"></span><span>Tidak Dibayar</span></div>
                        <div class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded bg-rose-100 border border-rose-300"></span><span>Libur Nasional / Weekend</span></div>
                    </div>
                </div>

                <!-- RIGHT 4 COLS: DETAIL KEGIATAN HARIAN -->
                <div class="lg:col-span-4 bg-white rounded-xl border border-slate-200 shadow-xs p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Detail Kegiatan Harian</span>
                                <h3 class="text-base font-bold text-slate-900 mt-0.5">
                                    {{ selectedDateFormatted }}
                                </h3>
                            </div>

                            <span
                                v-if="selectedHoliday"
                                class="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800"
                                :title="selectedHoliday.name"
                            >
                                {{ selectedHoliday.name }}
                            </span>
                            <span
                                v-else-if="isSelectedWeekend"
                                class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800"
                            >
                                Libur Weekend
                            </span>
                            <span
                                v-else
                                class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800"
                            >
                                Hari Kerja
                            </span>
                        </div>

                        <!-- List Kegiatan Pada Tanggal Terpilih -->
                        <div class="space-y-3 mt-4 max-h-[460px] overflow-y-auto">
                            <div
                                v-for="kegiatan in selectedEvents"
                                :key="kegiatan.id"
                                class="p-3.5 bg-slate-50 hover:bg-slate-100/70 rounded-xl border border-slate-200 transition space-y-2"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="text-[11px] font-semibold text-slate-500">
                                            {{ formatTime(kegiatan.start_datetime) }} - {{ formatTime(kegiatan.end_datetime) }} WIB
                                        </span>
                                        <h4 class="font-bold text-slate-900 text-xs mt-0.5">{{ kegiatan.title }}</h4>
                                    </div>
                                    <div class="flex items-center space-x-1.5 shrink-0">
                                        <span
                                            class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                            :class="[
                                                (typeof kegiatan.jenis_kegiatan?.payment_type === 'object' ? kegiatan.jenis_kegiatan?.payment_type.value : kegiatan.jenis_kegiatan?.payment_type) === 'unpaid'
                                                    ? 'bg-slate-100 text-slate-700 border-slate-200'
                                                    : ((typeof kegiatan.location_type === 'object' ? kegiatan.location_type.value : kegiatan.location_type) === 'luar_kota'
                                                        ? 'bg-purple-100 text-purple-800 border-purple-200'
                                                        : (kegiatan.is_weekend || kegiatan.is_holiday ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200'))
                                            ]"
                                        >
                                            {{ (typeof kegiatan.location_type === 'object' ? kegiatan.location_type.value : kegiatan.location_type) === 'luar_kota' ? 'Luar Kota' : 'Dalam Kota' }}
                                        </span>

                                        <Link
                                            :href="`/kegiatan/${kegiatan.id}/edit`"
                                            class="p-1 rounded text-slate-400 hover:text-blue-700 hover:bg-slate-200 transition"
                                            title="Ubah Jadwal"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </Link>

                                        <button
                                            type="button"
                                            @click="confirmDeleteKegiatan(kegiatan)"
                                            class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                            title="Hapus Kegiatan"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <p v-if="kegiatan.detail" class="text-[11px] text-slate-600 line-clamp-2">
                                    {{ kegiatan.detail }}
                                </p>

                                <div class="pt-2 border-t border-slate-200/60">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">
                                        Petugas Protokol ({{ kegiatan.protokols?.length || 0 }} orang)
                                    </span>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="proto in kegiatan.protokols"
                                            :key="proto.id"
                                            class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full bg-white border border-slate-200 text-xs font-semibold text-slate-700 shadow-2xs"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ proto.name }}</span>
                                        </span>
                                        <span v-if="!kegiatan.protokols || kegiatan.protokols.length === 0" class="text-rose-500 text-[11px]">
                                            Belum ada protokol ditugaskan
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedEvents.length === 0" class="text-center py-12 text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="text-xs font-medium">Tidak ada kegiatan di tanggal ini.</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Semua personil bebas penugasan.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="mt-6 pt-4 border-t border-slate-100">
                        <button
                            type="button"
                            @click="scheduleOnSelectedDate"
                            class="w-full py-2.5 px-3 rounded-lg bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold flex items-center justify-center space-x-1.5 shadow-xs transition cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>+ Jadwalkan Kegiatan Pada Tanggal Ini</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL KONFIRMASI HAPUS KEGIATAN -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" maxWidth="md">
            <div class="p-6">
                <div class="flex items-center space-x-3 text-rose-600 mb-4">
                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Kegiatan</h3>
                        <p class="text-xs text-slate-500">Jadwal dan seluruh penugasan personil akan dibatalkan.</p>
                    </div>
                </div>

                <div v-if="deletingKegiatan" class="bg-slate-50 p-3.5 rounded-xl border border-slate-100 text-xs text-slate-700 space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nama Acara:</span>
                        <span class="font-bold text-slate-900 text-right">{{ deletingKegiatan.title }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Waktu Pelaksanaan:</span>
                        <span class="font-semibold text-slate-900">
                            {{ new Date(deletingKegiatan.start_datetime).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }},
                            {{ formatTime(deletingKegiatan.start_datetime) }} WIB
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Personil Ditugaskan:</span>
                        <span class="font-semibold text-slate-900">{{ deletingKegiatan.protokols?.length || 0 }} orang personil</span>
                    </div>
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] mb-4 flex items-start space-x-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Penghapusan ini akan membebaskan kembali jadwal seluruh personil protokol terkait sehingga siap untuk ditugaskan pada agenda lain.</span>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <SecondaryButton @click="showDeleteModal = false" :disabled="deleteForm.processing">
                        Batal
                    </SecondaryButton>
                    <DangerButton @click="deleteKegiatan" :loading="deleteForm.processing">
                        Ya, Hapus Kegiatan
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
