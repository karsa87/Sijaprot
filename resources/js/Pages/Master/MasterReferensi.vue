<script setup>
import { ref, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    jenisKegiatans: {
        type: Array,
        required: true,
    },
    hariLiburs: {
        type: Array,
        required: true,
    },
    selectedYear: {
        type: [Number, String],
        default: new Date().getFullYear(),
    },
});

// Filter Tahun Hari Libur
const currentYear = ref(props.selectedYear);

watch(currentYear, (newYear) => {
    router.get(
        '/master/referensi',
        { year: newYear },
        { preserveState: true, preserveScroll: true, replace: true }
    );
});

// State Modal Jenis Kegiatan
const showJenisModal = ref(false);
const isEditingJenis = ref(false);
const editingJenisId = ref(null);

const jenisForm = useForm({
    name: '',
    code: '',
    payment_type: 'paid',
    payment_day_type: 'workday',
    location_type: 'dalam_kota',
    is_kunker: false,
    estimated_fee: 0,
    description: '',
});

const openCreateJenisModal = () => {
    isEditingJenis.value = false;
    editingJenisId.value = null;
    jenisForm.reset();
    jenisForm.clearErrors();
    jenisForm.payment_type = 'paid';
    jenisForm.payment_day_type = 'workday';
    jenisForm.location_type = 'dalam_kota';
    jenisForm.is_kunker = false;
    jenisForm.estimated_fee = 0;
    showJenisModal.value = true;
};

const openEditJenisModal = (j) => {
    isEditingJenis.value = true;
    editingJenisId.value = j.id;
    jenisForm.clearErrors();
    jenisForm.name = j.name;
    jenisForm.code = j.code;
    jenisForm.payment_type = typeof j.payment_type === 'object' ? j.payment_type.value : j.payment_type;
    jenisForm.payment_day_type = typeof j.payment_day_type === 'object' ? j.payment_day_type.value : (j.payment_day_type || 'workday');
    jenisForm.location_type = typeof j.location_type === 'object' ? j.location_type.value : j.location_type;
    jenisForm.is_kunker = Boolean(j.is_kunker);
    jenisForm.estimated_fee = Number(j.estimated_fee || 0);
    jenisForm.description = j.description || '';
    showJenisModal.value = true;
};

const saveJenisKegiatan = () => {
    if (isEditingJenis.value) {
        jenisForm.put(`/master/referensi/jenis/${editingJenisId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showJenisModal.value = false;
                jenisForm.reset();
            },
        });
    } else {
        jenisForm.post('/master/referensi/jenis', {
            preserveScroll: true,
            onSuccess: () => {
                showJenisModal.value = false;
                jenisForm.reset();
            },
        });
    }
};

// Safe Deletion Jenis Kegiatan
const showDeleteJenisModal = ref(false);
const deletingJenis = ref(null);
const isDeletingJenis = ref(false);

const confirmDeleteJenis = (j) => {
    deletingJenis.value = j;
    showDeleteJenisModal.value = true;
};

const executeDeleteJenis = () => {
    if (!deletingJenis.value) return;
    isDeletingJenis.value = true;
    router.delete(`/master/referensi/jenis/${deletingJenis.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteJenisModal.value = false;
            deletingJenis.value = null;
        },
        onFinish: () => {
            isDeletingJenis.value = false;
        },
    });
};

// State Modal Hari Libur
const showLiburModal = ref(false);
const isEditingLibur = ref(false);
const editingLiburId = ref(null);

const liburForm = useForm({
    tanggal: `${currentYear.value}-01-01`,
    name: '',
    type: 'nasional',
});

const openCreateLiburModal = () => {
    isEditingLibur.value = false;
    editingLiburId.value = null;
    liburForm.reset();
    liburForm.clearErrors();
    const today = new Date();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    liburForm.tanggal = `${currentYear.value}-${mm}-${dd}`;
    liburForm.type = 'nasional';
    showLiburModal.value = true;
};

const saveHariLibur = () => {
    if (isEditingLibur.value) {
        liburForm.put(`/master/referensi/libur/${editingLiburId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showLiburModal.value = false;
                liburForm.reset();
            },
        });
    } else {
        liburForm.post('/master/referensi/libur', {
            preserveScroll: true,
            onSuccess: () => {
                showLiburModal.value = false;
                liburForm.reset();
            },
        });
    }
};

// Helper Format Tipe Hari Pembayaran
const formatPaymentDayType = (type) => {
    const val = typeof type === 'object' ? type?.value : type;
    if (val === 'offday') return 'Luar Kerja';
    if (val === 'wfa') return 'WFA';
    return 'Kerja';
};

// Delete Hari Libur
const deletingLiburId = ref(null);
const deleteHariLibur = (hl) => {
    if (!confirm(`Yakin ingin menghapus hari libur '${hl.name}' (${hl.tanggal})?`)) return;
    deletingLiburId.value = hl.id;
    router.delete(`/master/referensi/libur/${hl.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingLiburId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Master Referensi & Libur Nasional" />

    <AppLayout>
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- LEFT 7 COLS: MASTER JENIS KEGIATAN -->
                <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Master Jenis Kegiatan (Template)</h3>
                            <p class="text-xs text-slate-500">Konfigurasi honorarium, kunjungan kerja, dan wilayah penugasan</p>
                        </div>
                        <button
                            type="button"
                            @click="openCreateJenisModal"
                            class="px-3 py-1.5 bg-[#1A5276] hover:bg-blue-800 active:bg-blue-900 text-white text-xs font-semibold rounded-lg shadow-2xs transition flex items-center space-x-1 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Jenis</span>
                        </button>
                    </div>

                    <div class="divide-y divide-slate-100 text-xs">
                        <div
                            v-for="j in jenisKegiatans"
                            :key="j.id"
                            class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition"
                        >
                            <div class="pr-3">
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-slate-900">{{ j.name }}</span>
                                    <code class="text-[10px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-600 font-mono">{{ j.code }}</code>
                                    <span
                                        v-if="j.is_kunker"
                                        class="px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-800 text-[9px] font-bold tracking-wide uppercase"
                                    >
                                        Kunker
                                    </span>
                                </div>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="text-slate-500 text-[11px]">{{ j.description || '-' }}</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-[10px] text-slate-400">
                                        {{ j.kegiatan_count > 0 ? `${j.kegiatan_count} kegiatan terkait` : 'Belum digunakan' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 shrink-0">
                                <!-- BADGE TIPE PEMBAYARAN -->
                                <span
                                    v-if="(typeof j.payment_type === 'object' ? j.payment_type.value : j.payment_type) === 'paid'"
                                    class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold text-[10px]"
                                >
                                    Dibayar ({{ formatPaymentDayType(j.payment_day_type) }})
                                </span>
                                <span
                                    v-else-if="(typeof j.payment_type === 'object' ? j.payment_type.value : j.payment_type) === 'conditional'"
                                    class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 font-semibold text-[10px] border border-sky-200"
                                >
                                    Kemungkinan Dibayar ({{ formatPaymentDayType(j.payment_day_type) }})
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[10px]"
                                >
                                    Tidak Dibayar
                                </span>

                                <!-- BADGE LOKASI -->
                                <span
                                    class="px-2 py-0.5 rounded font-semibold text-[10px]"
                                    :class="(typeof j.location_type === 'object' ? j.location_type.value : j.location_type) === 'luar_kota' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                                >
                                    {{ (typeof j.location_type === 'object' ? j.location_type.value : j.location_type) === 'luar_kota' ? 'Luar Kota' : 'Dalam Kota' }}
                                </span>

                                <!-- EDIT BUTTON -->
                                <button
                                    type="button"
                                    @click="openEditJenisModal(j)"
                                    class="p-1.5 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                                    title="Edit Template"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>

                                <!-- DELETE BUTTON -->
                                <button
                                    type="button"
                                    @click="confirmDeleteJenis(j)"
                                    class="p-1.5 rounded text-rose-400 hover:text-rose-700 hover:bg-rose-50 transition cursor-pointer"
                                    title="Hapus / Arsipkan Template"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div v-if="!jenisKegiatans || jenisKegiatans.length === 0" class="p-6 text-center text-slate-400">
                            Belum ada template jenis kegiatan.
                        </div>
                    </div>
                </div>

                <!-- RIGHT 5 COLS: MASTER HARI LIBUR NASIONAL -->
                <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Hari Libur Nasional</h3>
                            <p class="text-xs text-slate-500">Penentu otomatis kompensasi & status hari</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <select
                                v-model="currentYear"
                                class="px-2.5 py-1 text-xs border border-slate-200 rounded-lg bg-white font-semibold text-slate-700"
                            >
                                <option :value="2024">2024</option>
                                <option :value="2025">2025</option>
                                <option :value="2026">2026</option>
                                <option :value="2027">2027</option>
                            </select>
                            <button
                                type="button"
                                @click="openCreateLiburModal"
                                class="px-2.5 py-1 bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold rounded-md shadow-2xs transition flex items-center space-x-1 cursor-pointer"
                            >
                                <span>+ Tambah</span>
                            </button>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 text-xs">
                        <div
                            v-for="hl in hariLiburs"
                            :key="hl.id"
                            class="p-3 flex items-center justify-between hover:bg-slate-50 transition"
                        >
                            <div>
                                <span class="font-semibold text-slate-900 block">
                                    {{ new Date(hl.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                                </span>
                                <span class="text-slate-500 text-[11px]">{{ hl.name }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold"
                                    :class="(typeof hl.type === 'object' ? hl.type.value : hl.type) === 'nasional' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ (typeof hl.type === 'object' ? hl.type.value : hl.type) === 'nasional' ? 'Nasional' : ((typeof hl.type === 'object' ? hl.type.value : hl.type) === 'cuti_bersama' ? 'Cuti Bersama' : 'Hari Besar') }}
                                </span>
                                <button
                                    type="button"
                                    @click="deleteHariLibur(hl)"
                                    :disabled="deletingLiburId === hl.id"
                                    class="text-rose-500 hover:text-rose-700 p-1 cursor-pointer transition disabled:opacity-50"
                                    title="Hapus Hari Libur"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>

                        <div v-if="!hariLiburs || hariLiburs.length === 0" class="p-6 text-center text-slate-400">
                            Belum ada hari libur untuk tahun {{ currentYear }}.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL FORM JENIS KEGIATAN -->
        <Modal :show="showJenisModal" @close="showJenisModal = false" maxWidth="md">
            <div class="p-6 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ isEditingJenis ? 'Edit Jenis Kegiatan' : 'Tambah Jenis Kegiatan' }}
                    </h3>
                    <button
                        type="button"
                        @click="showJenisModal = false"
                        class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveJenisKegiatan" class="space-y-4 mt-4">
                    <div>
                        <InputLabel value="Nama Jenis Kegiatan *" />
                        <TextInput
                            v-model="jenisForm.name"
                            type="text"
                            class="w-full mt-1"
                            placeholder="Contoh: Kunjungan Kerja Daerah Pimpinan"
                            required
                        />
                        <InputError :message="jenisForm.errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Kode Unik *" />
                            <TextInput
                                v-model="jenisForm.code"
                                type="text"
                                class="w-full mt-1 uppercase font-mono"
                                placeholder="KUNKER_DAERAH"
                                required
                            />
                            <InputError :message="jenisForm.errors.code" />
                        </div>
                        <div>
                            <InputLabel value="Wilayah Dinas *" />
                            <select
                                v-model="jenisForm.location_type"
                                class="w-full mt-1 px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-xs"
                            >
                                <option value="dalam_kota">Dalam Kota</option>
                                <option value="luar_kota">Luar Kota (H+1 Gap)</option>
                            </select>
                        </div>
                    </div>

                    <!-- KLASIFIKASI KUNKER TOGGLE -->
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Klasifikasi Kunjungan Kerja (Kunker)</span>
                            <span class="text-[11px] text-slate-500">Kegiatan dengan template ini otomatis ditandai sebagai Kunker</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="jenisForm.is_kunker"
                                class="sr-only peer"
                            />
                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#1A5276]"></div>
                        </label>
                    </div>

                    <!-- 3 PILIHAN TIPE PEMBAYARAN -->
                    <div>
                        <InputLabel value="Tipe Pembayaran (Honorarium) *" />
                        <div class="grid grid-cols-3 gap-2 mt-1">
                            <label
                                class="flex flex-col p-2.5 rounded-lg border cursor-pointer transition text-center"
                                :class="jenisForm.payment_type === 'paid' ? 'border-emerald-500 bg-emerald-50/50 text-emerald-900 ring-1 ring-emerald-500' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input
                                    type="radio"
                                    value="paid"
                                    v-model="jenisForm.payment_type"
                                    class="sr-only"
                                />
                                <span class="font-bold text-[11px]">Dibayar</span>
                                <span class="text-[10px] text-slate-500 mt-0.5">Honor Pasti</span>
                            </label>

                            <label
                                class="flex flex-col p-2.5 rounded-lg border cursor-pointer transition text-center"
                                :class="jenisForm.payment_type === 'conditional' ? 'border-sky-500 bg-sky-50/50 text-sky-900 ring-1 ring-sky-500' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input
                                    type="radio"
                                    value="conditional"
                                    v-model="jenisForm.payment_type"
                                    class="sr-only"
                                />
                                <span class="font-bold text-[11px]">Kemungkinan</span>
                                <span class="text-[10px] text-slate-500 mt-0.5">Tentatif DIPA</span>
                            </label>

                            <label
                                class="flex flex-col p-2.5 rounded-lg border cursor-pointer transition text-center"
                                :class="jenisForm.payment_type === 'unpaid' ? 'border-slate-500 bg-slate-100 text-slate-900 ring-1 ring-slate-500' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input
                                    type="radio"
                                    value="unpaid"
                                    v-model="jenisForm.payment_type"
                                    class="sr-only"
                                />
                                <span class="font-bold text-[11px]">Tidak Dibayar</span>
                                <span class="text-[10px] text-slate-500 mt-0.5">Rutin / Bebas</span>
                            </label>
                        </div>
                        <InputError :message="jenisForm.errors.payment_type" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Kategori Hari (Honor)" />
                            <select
                                v-model="jenisForm.payment_day_type"
                                :disabled="jenisForm.payment_type === 'unpaid'"
                                class="w-full mt-1 px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-xs disabled:bg-slate-100 disabled:text-slate-400"
                            >
                                <option value="workday">Hari Kerja (Senin–Kamis)</option>
                                <option value="wfa">Jum'at (WFA)</option>
                                <option value="offday">Luar Kerja (Weekend/Libur)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Estimasi Honor (Rp)" />
                            <TextInput
                                v-model="jenisForm.estimated_fee"
                                type="number"
                                step="10000"
                                min="0"
                                max="999999999"
                                class="w-full mt-1"
                                :disabled="jenisForm.payment_type === 'unpaid'"
                            />
                            <InputError :message="jenisForm.errors.estimated_fee" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Keterangan / Catatan SOP" />
                        <textarea
                            v-model="jenisForm.description"
                            rows="2"
                            class="w-full mt-1 px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] text-xs"
                            placeholder="Catatan panduan operasional kegiatan"
                        ></textarea>
                        <InputError :message="jenisForm.errors.description" />
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                        <SecondaryButton @click="showJenisModal = false" :disabled="jenisForm.processing">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :loading="jenisForm.processing">
                            Simpan Template
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL KONFIRMASI SAFE DELETION JENIS KEGIATAN -->
        <Modal :show="showDeleteJenisModal" @close="showDeleteJenisModal = false" maxWidth="md">
            <div class="p-6 text-xs" v-if="deletingJenis">
                <div class="flex items-center space-x-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Konfirmasi Hapus Template</h3>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingJenis.name }} ({{ deletingJenis.code }})</p>
                    </div>
                </div>

                <div class="py-4 space-y-3">
                    <div
                        v-if="deletingJenis.kegiatan_count > 0"
                        class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs leading-relaxed"
                    >
                        <div class="font-bold flex items-center space-x-1.5 mb-1">
                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Template Terikat Pada {{ deletingJenis.kegiatan_count }} Kegiatan</span>
                        </div>
                        <p>
                            Template ini memiliki riwayat dinas aktif. Demi menjaga keutuhan laporan dan integritas data, template akan <strong>Diarsipkan (Soft Delete)</strong>. Template tidak akan muncul pada pilihan kegiatan baru, namun riwayat penugasan masa lalu tetap aman 100%.
                        </p>
                    </div>

                    <div
                        v-else
                        class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-slate-700 text-xs leading-relaxed"
                    >
                        <p>
                            Template ini belum pernah digunakan pada kegiatan manapun. Sistem akan menghapus template ini <strong>secara permanen</strong> dari basis data.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <SecondaryButton @click="showDeleteJenisModal = false" :disabled="isDeletingJenis">
                        Batal
                    </SecondaryButton>
                    <DangerButton @click="executeDeleteJenis" :loading="isDeletingJenis">
                        {{ deletingJenis.kegiatan_count > 0 ? 'Arsipkan Template' : 'Hapus Permanen' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL FORM HARI LIBUR -->
        <Modal :show="showLiburModal" @close="showLiburModal = false" maxWidth="sm">
            <div class="p-6 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Tambah Hari Libur</h3>
                    <button
                        type="button"
                        @click="showLiburModal = false"
                        class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveHariLibur" class="space-y-3 mt-4">
                    <div>
                        <InputLabel value="Tanggal Libur *" />
                        <TextInput
                            v-model="liburForm.tanggal"
                            type="date"
                            class="w-full"
                            required
                        />
                        <InputError :message="liburForm.errors.tanggal" />
                    </div>

                    <div>
                        <InputLabel value="Nama Hari Libur / Peringatan *" />
                        <TextInput
                            v-model="liburForm.name"
                            type="text"
                            placeholder="Contoh: Hari Kemerdekaan RI"
                            class="w-full"
                            required
                        />
                        <InputError :message="liburForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel value="Kategori Libur *" />
                        <select
                            v-model="liburForm.type"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-xs"
                        >
                            <option value="nasional">Libur Nasional</option>
                            <option value="cuti_bersama">Cuti Bersama</option>
                            <option value="hari_besar">Hari Besar / Peringatan</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                        <SecondaryButton @click="showLiburModal = false" :disabled="liburForm.processing">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :loading="liburForm.processing">
                            Simpan Libur
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
