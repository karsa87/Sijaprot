<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    isEditing: {
        type: Boolean,
        default: false,
    },
    kegiatan: {
        type: Object,
        default: null,
    },
    defaultDate: {
        type: String,
        required: true,
    },
    jenisList: {
        type: Array,
        required: true,
    },
    activeProtokols: {
        type: Array,
        required: true,
    },
    holidays: {
        type: Array,
        default: () => [],
    },
});

// Form Reactive State
const form = useForm({
    title: props.kegiatan?.title || '',
    jenis_kegiatan_id: props.kegiatan?.jenis_kegiatan_id || (props.jenisList[0]?.id ?? ''),
    jumlah_protokol: props.kegiatan?.jumlah_protokol || 2,
    start_date: props.kegiatan?.start_date || props.defaultDate,
    start_time: props.kegiatan?.start_time || '08:30',
    end_date: props.kegiatan?.end_date || props.defaultDate,
    end_time: props.kegiatan?.end_time || '12:00',
    is_kunker: props.kegiatan?.is_kunker !== undefined ? Boolean(props.kegiatan.is_kunker) : Boolean(props.jenisList[0]?.is_kunker ?? false),
    detail: props.kegiatan?.detail || '',
    recommendations: props.kegiatan?.recommendations || [],
});

// Simulation UI State
const isSimulating = ref(false);
const simulationWarning = ref(null);
const hasRunSimulation = computed(() => form.recommendations && form.recommendations.length > 0);

// Client-Side Auto Detection (100% Instant, BUG-03 & BUG-05 Compliance)
const selectedJenis = computed(() => {
    return props.jenisList.find((j) => j.id == form.jenis_kegiatan_id);
});

// Auto-align end_date when start_date changes on new form
watch(() => form.start_date, (newDate) => {
    if (!props.isEditing && newDate) {
        form.end_date = newDate;
    }
});

// Auto-sync is_kunker when template changed (for new activity)
watch(() => form.jenis_kegiatan_id, (newJenisId) => {
    if (!props.isEditing && newJenisId) {
        const found = props.jenisList.find((j) => j.id == newJenisId);
        if (found) {
            form.is_kunker = Boolean(found.is_kunker);
        }
    }
});

// Multi-day Weekend, Friday WFA & Holiday Scanning
const detection = computed(() => {
    if (!form.start_date) {
        return {
            isWeekend: false,
            isHoliday: false,
            isWfa: false,
            isKunker: Boolean(form.is_kunker),
            feeType: 'paid',
            hari: 'Hari Kerja Biasa',
            fee: 'Dibayar',
            lokasi: 'Dalam Kota',
        };
    }

    const start = new Date(form.start_date);
    const end = new Date(form.end_date || form.start_date);
    const holidayDates = new Set(props.holidays.map((h) => h.tanggal.substring(0, 10)));

    let hasWeekend = false;
    let hasHoliday = false;
    let hasWfa = false;
    let holidayName = null;

    const curr = new Date(start);
    while (curr <= end) {
        const dayOfWeek = curr.getDay(); // 0 = Minggu, 5 = Jumat, 6 = Sabtu
        const dateStr = curr.toISOString().substring(0, 10);
        const isThisDayHoliday = holidayDates.has(dateStr);

        if (dayOfWeek === 0 || dayOfWeek === 6) {
            hasWeekend = true;
        }

        if (isThisDayHoliday) {
            hasHoliday = true;
            if (!holidayName) {
                const found = props.holidays.find((h) => h.tanggal.substring(0, 10) === dateStr);
                holidayName = found ? found.name : 'Libur Nasional';
            }
        } else if (dayOfWeek === 5) {
            hasWfa = true;
        }

        curr.setDate(curr.getDate() + 1);
    }

    // Check if template explicitly designates WFA
    const jenisDayType = typeof selectedJenis.value?.payment_day_type === 'object'
        ? selectedJenis.value?.payment_day_type?.value
        : selectedJenis.value?.payment_day_type;
    if (jenisDayType === 'wfa' && !hasHoliday) {
        hasWfa = true;
    }

    // Label Hari
    let hari = 'Hari Kerja Biasa';
    if (hasHoliday) {
        hari = `Libur: ${holidayName || 'Libur Nasional'}`;
    } else if (hasWeekend) {
        hari = start.toDateString() === end.toDateString() ? 'Akhir Pekan (Weekend)' : 'Rentang Akhir Pekan (Weekend)';
    } else if (hasWfa) {
        hari = "Jum'at (WFA)";
    }

    // Label Fee & Payment Type
    const jenisPaymentType = typeof selectedJenis.value?.payment_type === 'object'
        ? selectedJenis.value?.payment_type?.value
        : selectedJenis.value?.payment_type;

    const feeType = jenisPaymentType || 'paid';
    let fee = 'Dibayar (Hari Kerja)';

    if (feeType === 'unpaid') {
        fee = 'Tidak Dibayar (Rutin)';
    } else if (feeType === 'conditional') {
        if (hasWeekend || hasHoliday) {
            fee = 'Kemungkinan Dibayar (Luar Kerja)';
        } else if (hasWfa) {
            fee = "Kemungkinan Dibayar (WFA)";
        } else {
            fee = 'Kemungkinan Dibayar (Hari Kerja)';
        }
    } else {
        if (hasWeekend || hasHoliday) {
            fee = 'Dibayar (Luar Kerja)';
        } else if (hasWfa) {
            fee = "Dibayar (WFA)";
        } else {
            fee = 'Dibayar (Hari Kerja)';
        }
    }

    // Label Lokasi
    const lokasi = selectedJenis.value && (typeof selectedJenis.value.location_type === 'object' ? selectedJenis.value.location_type.value : selectedJenis.value.location_type) === 'luar_kota'
        ? 'Luar Kota (H+1 Gap)'
        : 'Dalam Kota';

    return {
        isWeekend: hasWeekend,
        isHoliday: hasHoliday,
        isWfa: hasWfa,
        isKunker: Boolean(form.is_kunker),
        feeType,
        hari,
        fee,
        lokasi,
    };
});

// Negative Duration Validation Feedback
const isDurationNegative = computed(() => {
    if (!form.start_date || !form.end_date || !form.start_time || !form.end_time) return false;
    const s = new Date(`${form.start_date}T${form.start_time}`);
    const e = new Date(`${form.end_date}T${form.end_time}`);
    return e <= s;
});

// Run Simulation (Server-Backed Engine)
const runSimulation = async () => {
    form.clearErrors();
    if (isDurationNegative.value) {
        form.setError('end_date', 'Waktu selesai kegiatan harus lebih akhir dari waktu mulai kegiatan.');
        return;
    }

    isSimulating.value = true;
    simulationWarning.value = null;

    try {
        const response = await window.axios.post('/kegiatan/simulasi', {
            title: form.title,
            jenis_kegiatan_id: form.jenis_kegiatan_id,
            jumlah_protokol: form.jumlah_protokol,
            start_date: form.start_date,
            start_time: form.start_time,
            end_date: form.end_date,
            end_time: form.end_time,
            is_kunker: form.is_kunker,
            detail: form.detail,
            kegiatan_id: props.kegiatan?.id,
        });

        form.recommendations = response.data.recommendations || [];
        simulationWarning.value = response.data.warning;
    } catch (error) {
        if (error.response?.data?.errors) {
            for (const [key, msgs] of Object.entries(error.response.data.errors)) {
                form.setError(key, msgs[0]);
            }
        } else {
            alert('Gagal menjalankan simulasi: ' + (error.response?.data?.message || error.message));
        }
    } finally {
        isSimulating.value = false;
    }
};

// Modal Swap Protocol State
const showSwapModal = ref(false);
const swapOldProto = ref(null);
const swapNewProtoId = ref('');
const swapReason = ref('Penyesuaian kebutuhan protokoler dinas');
const swapError = ref('');
const isSwapping = ref(false);

const openSwapModal = (proto) => {
    swapOldProto.value = proto;
    swapNewProtoId.value = '';
    swapReason.value = 'Penyesuaian kebutuhan protokoler dinas';
    swapError.value = '';
    showSwapModal.value = true;
};

const availableForSwap = computed(() => {
    const selectedIds = new Set((form.recommendations || []).map((r) => r.protokol.id));
    return props.activeProtokols.filter((p) => !selectedIds.has(p.id));
});

const applySwap = async () => {
    if (!swapNewProtoId.value) {
        swapError.value = 'Pilih personil protokol pengganti.';
        return;
    }

    isSwapping.value = true;
    swapError.value = '';

    try {
        await window.axios.post('/kegiatan/validate-overlap', {
            protokol_id: swapNewProtoId.value,
            start_date: form.start_date,
            start_time: form.start_time,
            end_date: form.end_date,
            end_time: form.end_time,
            jenis_kegiatan_id: form.jenis_kegiatan_id,
            kegiatan_id: props.kegiatan?.id,
        });

        const newProto = props.activeProtokols.find((p) => p.id == swapNewProtoId.value);
        if (newProto && swapOldProto.value) {
            const index = form.recommendations.findIndex((r) => r.protokol.id === swapOldProto.value.id);
            if (index !== -1) {
                form.recommendations[index] = {
                    protokol: newProto,
                    score: 90.0,
                    reason: `Penggantian Manual: ${swapReason.value}`,
                };
            }
        }
        showSwapModal.value = false;
    } catch (e) {
        swapError.value = e.response?.data?.message || 'Gagal memverifikasi ketersediaan jadwal personil.';
    } finally {
        isSwapping.value = false;
    }
};

// Modal Add Manual Protocol State
const showAddManualModal = ref(false);
const manualProtoId = ref('');
const manualReason = ref('Dipilih secara manual oleh Admin');
const manualError = ref('');
const isAddingManual = ref(false);

const openAddManualModal = () => {
    manualProtoId.value = '';
    manualReason.value = 'Dipilih secara manual oleh Admin';
    manualError.value = '';
    showAddManualModal.value = true;
};

const applyAddManual = async () => {
    if (!manualProtoId.value) {
        manualError.value = 'Pilih personil protokol yang ingin ditambahkan.';
        return;
    }

    isAddingManual.value = true;
    manualError.value = '';

    try {
        await window.axios.post('/kegiatan/validate-overlap', {
            protokol_id: manualProtoId.value,
            start_date: form.start_date,
            start_time: form.start_time,
            end_date: form.end_date,
            end_time: form.end_time,
            jenis_kegiatan_id: form.jenis_kegiatan_id,
            kegiatan_id: props.kegiatan?.id,
        });

        const newProto = props.activeProtokols.find((p) => p.id == manualProtoId.value);
        if (newProto) {
            form.recommendations.push({
                protokol: newProto,
                score: 85.0,
                reason: `Manual: ${manualReason.value}`,
            });
        }
        showAddManualModal.value = false;
    } catch (e) {
        manualError.value = e.response?.data?.message || 'Gagal menambahkan personil: jadwal bentrok.';
    } finally {
        isAddingManual.value = false;
    }
};

// Remove Protocol from List
const removeProtocol = (protoId) => {
    form.recommendations = form.recommendations.filter((r) => r.protokol.id !== protoId);
};

// Save Action
const submitForm = () => {
    if (isDurationNegative.value) {
        form.setError('end_date', 'Waktu selesai kegiatan harus lebih akhir dari waktu mulai kegiatan.');
        return;
    }

    if (props.isEditing) {
        form.put(`/kegiatan/${props.kegiatan.id}`);
    } else {
        form.post('/kegiatan');
    }
};

// Delete Action
const showDeleteModal = ref(false);
const deleteForm = useForm({});
const deleteKegiatan = () => {
    deleteForm.delete(`/kegiatan/${props.kegiatan.id}`);
};
</script>

<template>
    <Head :title="isEditing ? 'Ubah Kegiatan & Penugasan' : 'Buat Kegiatan & Auto-Scheduling'" />

    <AppLayout>
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <!-- Header Card -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Form Ubah Kegiatan & Penugasan Protokol' : 'Form Buat Kegiatan & Engine Penjadwalan Otomatis' }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Perbarui rincian kegiatan atau sesuaikan petugas protokol yang ditugaskan.' : 'Sistem secara instan menyeleksi kandidat protokol teradil berdasarkan 4 Hard Rules & 4 Soft Rules.' }}
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span v-if="isEditing" class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-1 rounded-full shrink-0">
                            Mode Edit #{{ kegiatan.id }}
                        </span>
                        <Link href="/dashboard" class="text-xs text-slate-500 hover:text-slate-800 font-medium">
                            Kembali ke Kalender
                        </Link>
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <!-- LEFT 7 COLS: FORM INPUT -->
                    <div class="lg:col-span-7 space-y-4">
                        <div>
                            <InputLabel value="Judul / Nama Kegiatan *" />
                            <TextInput
                                v-model="form.title"
                                type="text"
                                class="w-full"
                                placeholder="Contoh: Penyambutan Kunjungan Kerja Tamu Negara"
                                required
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Template Jenis Kegiatan *" />
                                <select
                                    v-model="form.jenis_kegiatan_id"
                                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1A5276] bg-white outline-none"
                                >
                                    <option v-for="j in jenisList" :key="j.id" :value="j.id">
                                        {{ j.name }} ({{ (typeof j.location_type === 'object' ? j.location_type.value : j.location_type) === 'luar_kota' ? 'Luar Kota' : 'Dalam Kota' }}{{ j.is_kunker ? ' • Kunker' : '' }})
                                    </option>
                                </select>
                                <InputError :message="form.errors.jenis_kegiatan_id" />
                            </div>

                            <div>
                                <InputLabel value="Jumlah Protokol Dibutuhkan *" />
                                <div class="flex items-center space-x-2">
                                    <TextInput
                                        v-model.number="form.jumlah_protokol"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-24 font-bold text-center"
                                        required
                                    />
                                    <span class="text-xs text-slate-500">orang personil</span>
                                </div>
                                <InputError :message="form.errors.jumlah_protokol" />
                            </div>
                        </div>

                        <!-- KLASIFIKASI KUNJUNGAN KERJA (KUNKER) TOGGLE -->
                        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-slate-800 text-xs">Agenda Kunjungan Kerja (Kunker)</span>
                                    <span
                                        v-if="form.is_kunker"
                                        class="px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-800 text-[9px] font-bold uppercase tracking-wider"
                                    >
                                        Kunker Aktif
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-500 mt-0.5 block">
                                    {{ form.is_kunker ? 'Ditandai sebagai agenda dinas Kunker. Algoritma akan memprioritaskan personil yang belum pernah Kunker bulan ini.' : 'Dinas seremonial / kegiatan biasa internal non-kunker.' }}
                                </span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input
                                    type="checkbox"
                                    v-model="form.is_kunker"
                                    class="sr-only peer"
                                />
                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#1A5276]"></div>
                            </label>
                        </div>

                        <!-- Tanggal & Jam Mulai / Selesai -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                                <span class="text-[11px] font-bold text-slate-700 uppercase block mb-1">Waktu Mulai</span>
                                <div class="grid grid-cols-2 gap-2">
                                    <TextInput v-model="form.start_date" type="date" class="px-2 py-1.5 text-xs bg-white" required />
                                    <TextInput v-model="form.start_time" type="time" class="px-2 py-1.5 text-xs bg-white" required />
                                </div>
                                <InputError :message="form.errors.start_date" />
                                <InputError :message="form.errors.start_time" />
                            </div>

                            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200" :class="isDurationNegative ? 'border-rose-400 bg-rose-50/50' : ''">
                                <span class="text-[11px] font-bold text-slate-700 uppercase block mb-1">Waktu Selesai</span>
                                <div class="grid grid-cols-2 gap-2">
                                    <TextInput v-model="form.end_date" type="date" class="px-2 py-1.5 text-xs bg-white" required />
                                    <TextInput v-model="form.end_time" type="time" class="px-2 py-1.5 text-xs bg-white" required />
                                </div>
                                <InputError :message="form.errors.end_date" />
                                <InputError :message="form.errors.end_time" />
                                <span v-if="isDurationNegative" class="text-rose-600 text-[10px] block mt-1 font-semibold">
                                    ⚠️ Waktu selesai harus lebih akhir dari waktu mulai.
                                </span>
                            </div>
                        </div>

                        <!-- Auto-detected status bar -->
                        <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl space-y-2">
                            <span class="text-[11px] font-bold text-[#1A5276] uppercase tracking-wider block">
                                Klasifikasi Otomatis Terdeteksi Sistem
                            </span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                <!-- 1. Kategori Hari -->
                                <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                                    <span class="text-[10px] text-slate-400 block">Kategori Hari</span>
                                    <span
                                        class="font-bold text-xs"
                                        :class="detection.isWfa ? 'text-sky-700' : ((detection.isWeekend || detection.isHoliday) ? 'text-rose-600' : 'text-slate-700')"
                                    >
                                        {{ detection.hari }}
                                    </span>
                                </div>

                                <!-- 2. Status Honorarium -->
                                <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                                    <span class="text-[10px] text-slate-400 block">Honorarium / Fee</span>
                                    <span
                                        class="font-bold text-xs"
                                        :class="detection.feeType === 'paid' ? 'text-emerald-600' : (detection.feeType === 'conditional' ? 'text-sky-600' : 'text-slate-500')"
                                    >
                                        {{ detection.fee }}
                                    </span>
                                </div>

                                <!-- 3. Agenda Dinas (Kunker) -->
                                <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                                    <span class="text-[10px] text-slate-400 block">Agenda Dinas</span>
                                    <span
                                        class="font-bold text-xs"
                                        :class="form.is_kunker ? 'text-violet-700 font-bold' : 'text-slate-600'"
                                    >
                                        {{ form.is_kunker ? 'Kunker Pimpinan' : 'Bukan Kunker' }}
                                    </span>
                                </div>

                                <!-- 4. Wilayah Dinas -->
                                <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                                    <span class="text-[10px] text-slate-400 block">Wilayah Dinas</span>
                                    <span class="font-bold text-xs" :class="detection.lokasi.includes('Luar') ? 'text-purple-700' : 'text-blue-700'">
                                        {{ detection.lokasi }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Detail / Catatan Rundown Acara" />
                            <textarea
                                v-model="form.detail"
                                rows="2"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1A5276] outline-none"
                                placeholder="Rundown, dress code (PSH/Batik), penjemputan, dsb."
                            ></textarea>
                            <InputError :message="form.errors.detail" />
                        </div>

                        <!-- Run Simulation Button with Loading Interaction -->
                        <button
                            type="button"
                            @click="runSimulation"
                            :disabled="isSimulating || isDurationNegative"
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-[#1A5276] to-blue-700 hover:from-blue-800 hover:to-blue-900 text-white font-bold text-xs flex items-center justify-center space-x-2 shadow-md transition transform active:scale-[0.99] disabled:opacity-50 cursor-pointer"
                        >
                            <svg v-if="!isSimulating" class="w-4 h-4 text-yellow-300 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ isSimulating ? 'Sedang Menganalisis 5 Parameter Algoritma...' : (isEditing ? 'Jalankan Ulang Algoritma Penjadwalan' : 'Jalankan Algoritma Penjadwalan Otomatis') }}</span>
                        </button>
                    </div>

                    <!-- RIGHT 5 COLS: HASIL REKOMENDASI ENGINE -->
                    <div class="lg:col-span-5 bg-slate-50 p-5 rounded-xl border border-slate-200 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                                <span class="text-xs font-bold text-slate-900 flex items-center space-x-1.5">
                                    <span class="w-2 h-2 rounded-full" :class="hasRunSimulation ? 'bg-emerald-500 animate-ping' : 'bg-slate-300'"></span>
                                    <span>{{ isEditing ? 'Susunan Petugas Protokol' : 'Hasil Rekomendasi Algoritma' }}</span>
                                </span>
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded"
                                    :class="hasRunSimulation ? 'text-emerald-700 bg-emerald-100' : 'text-slate-500 bg-slate-200'"
                                >
                                    {{ hasRunSimulation ? 'Petugas Terjadwal' : 'Menunggu Simulasi' }}
                                </span>
                            </div>

                            <div v-if="simulationWarning" class="mt-3 p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800">
                                {{ simulationWarning }}
                            </div>

                            <!-- Skeleton Loading State when Simulating -->
                            <div v-if="isSimulating" class="space-y-3 mt-4 animate-pulse">
                                <div v-for="n in form.jumlah_protokol" :key="n" class="p-3 bg-white rounded-xl border border-slate-200 space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-5 h-5 bg-slate-200 rounded-full"></div>
                                        <div class="h-3 bg-slate-200 rounded w-32"></div>
                                    </div>
                                    <div class="h-2 bg-slate-100 rounded w-full"></div>
                                </div>
                            </div>

                            <!-- Candidates List -->
                            <div v-else class="space-y-3 mt-4">
                                <div
                                    v-for="(rec, idx) in form.recommendations"
                                    :key="rec.protokol.id"
                                    class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs space-y-2 transition hover:shadow-xs"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-5 h-5 rounded-full bg-[#1A5276] text-white flex items-center justify-center font-bold text-[10px] shrink-0">
                                                #{{ idx + 1 }}
                                            </span>
                                            <div>
                                                <h4 class="font-bold text-slate-900 text-xs">{{ rec.protokol.name }}</h4>
                                                <span class="text-[10px] text-blue-700 font-medium font-mono">{{ rec.protokol.phone_wa }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1 shrink-0">
                                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                Skor: {{ Number(rec.score || 0).toFixed(1) }}
                                            </span>
                                            <!-- Tombol Swap -->
                                            <button
                                                type="button"
                                                @click="openSwapModal(rec.protokol)"
                                                class="px-2 py-0.5 text-[11px] font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 rounded shadow-2xs transition flex items-center space-x-1 cursor-pointer"
                                                title="Ganti personil protokol ini"
                                            >
                                                <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                </svg>
                                                <span>Ganti</span>
                                            </button>
                                            <!-- Tombol Remove -->
                                            <button
                                                type="button"
                                                @click="removeProtocol(rec.protokol.id)"
                                                class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition cursor-pointer"
                                                title="Lepaskan personil ini dari tugas"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <p class="text-[11px] text-slate-600 leading-relaxed bg-slate-50 p-2 rounded-lg border border-slate-100">
                                        {{ rec.reason }}
                                    </p>

                                    <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                                        <span>Hard Rules: ✅ Lolos</span>
                                        <span class="text-emerald-600 font-semibold">Tersedia & Siap Dinas</span>
                                    </div>
                                </div>

                                <!-- Add Manual Protocol Button -->
                                <div class="pt-1">
                                    <button
                                        type="button"
                                        @click="openAddManualModal"
                                        class="w-full py-2 px-3 border border-dashed border-slate-300 hover:border-[#1A5276] hover:bg-white text-slate-600 hover:text-[#1A5276] rounded-xl text-xs font-semibold flex items-center justify-center space-x-1.5 transition cursor-pointer"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>+ Tambah / Pilih Personil Secara Manual</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Actions: Save & Delete -->
                        <div class="mt-6 pt-4 border-t border-slate-200 space-y-2">
                            <button
                                type="button"
                                @click="submitForm"
                                :disabled="form.processing || (!hasRunSimulation && !isEditing)"
                                class="w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition flex items-center justify-center space-x-1.5 disabled:opacity-50 cursor-pointer"
                            >
                                <svg v-if="!form.processing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>{{ form.processing ? 'Menyimpan Jadwal...' : (isEditing ? 'Simpan Perubahan Jadwal Kegiatan' : 'Konfirmasi & Simpan Jadwal Kegiatan') }}</span>
                            </button>

                            <button
                                v-if="isEditing"
                                type="button"
                                @click="showDeleteModal = true"
                                class="w-full py-2 px-4 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition flex items-center justify-center space-x-1.5 cursor-pointer"
                            >
                                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Hapus Kegiatan Ini</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL GANTI PROTOKOL (DIRECT SWAP) -->
        <Modal :show="showSwapModal" @close="showSwapModal = false" maxWidth="md">
            <div class="p-6 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Ganti Personil Protokol</h3>
                    <button type="button" @click="showSwapModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-3 mt-4">
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <span class="text-[10px] text-slate-500 block">Personil yang akan diganti:</span>
                        <span class="font-bold text-slate-900 text-sm">{{ swapOldProto?.name }}</span>
                    </div>

                    <div>
                        <InputLabel value="Pilih Personil Pengganti *" />
                        <select
                            v-model="swapNewProtoId"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-xs"
                        >
                            <option value="">-- Pilih Protokol --</option>
                            <option v-for="p in availableForSwap" :key="p.id" :value="p.id">
                                {{ p.name }} ({{ p.phone_wa }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel value="Alasan Penggantian *" />
                        <TextInput
                            v-model="swapReason"
                            type="text"
                            class="w-full"
                            placeholder="Alasan tugas, rotasi, dll."
                        />
                    </div>

                    <div v-if="swapError" class="p-2.5 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-xs">
                        ⚠️ {{ swapError }}
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                        <SecondaryButton @click="showSwapModal = false" :disabled="isSwapping">Batal</SecondaryButton>
                        <PrimaryButton @click="applySwap" :loading="isSwapping">Terapkan Pergantian</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL TAMBAH PERSONIL MANUAL -->
        <Modal :show="showAddManualModal" @close="showAddManualModal = false" maxWidth="md">
            <div class="p-6 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Tambah Personil Secara Manual</h3>
                    <button type="button" @click="showAddManualModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-3 mt-4">
                    <div>
                        <InputLabel value="Pilih Personil Protokol *" />
                        <select
                            v-model="manualProtoId"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-xs"
                        >
                            <option value="">-- Pilih Protokol --</option>
                            <option v-for="p in availableForSwap" :key="p.id" :value="p.id">
                                {{ p.name }} ({{ p.phone_wa }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel value="Alasan Penugasan Manual *" />
                        <TextInput
                            v-model="manualReason"
                            type="text"
                            class="w-full"
                            placeholder="Alasan penugasan khusus"
                        />
                    </div>

                    <div v-if="manualError" class="p-2.5 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-xs">
                        ⚠️ {{ manualError }}
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                        <SecondaryButton @click="showAddManualModal = false" :disabled="isAddingManual">Batal</SecondaryButton>
                        <PrimaryButton @click="applyAddManual" :loading="isAddingManual">Tambahkan ke Daftar</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

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

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] mb-4">
                    Penghapusan ini akan membebaskan kembali jadwal seluruh personil protokol terkait sehingga siap untuk ditugaskan pada agenda lain.
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <SecondaryButton @click="showDeleteModal = false" :disabled="deleteForm.processing">Batal</SecondaryButton>
                    <DangerButton @click="deleteKegiatan" :loading="deleteForm.processing">Ya, Hapus Kegiatan</DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
