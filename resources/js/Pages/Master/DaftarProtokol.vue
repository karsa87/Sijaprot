<script setup>
import { ref, watch } from 'vue';
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
    protokols: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', status: 'all', month: new Date().getMonth() + 1, year: new Date().getFullYear() }),
    },
});

// Search and Filter State
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const selectedMonth = ref(props.filters.month || new Date().getMonth() + 1);
const selectedYear = ref(props.filters.year || new Date().getFullYear());

let searchTimeout = null;
watch([search, statusFilter, selectedMonth, selectedYear], () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            '/master/protokol',
            {
                search: search.value,
                status: statusFilter.value,
                month: selectedMonth.value,
                year: selectedYear.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }, 300);
});

// Form Create / Edit Modal State
const showFormModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    phone_wa: '',
    nip: '',
    status: 'active',
    notes: '',
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    showFormModal.value = true;
};

const openEditModal = (p) => {
    isEditing.value = true;
    editingId.value = p.id;
    form.clearErrors();
    form.name = p.name;
    form.phone_wa = p.phone_wa;
    form.nip = p.nip || '';
    form.status = typeof p.status === 'object' ? p.status.value : p.status;
    form.notes = p.notes || '';
    showFormModal.value = true;
};

const saveProtokol = () => {
    if (isEditing.value) {
        form.put(`/master/protokol/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showFormModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/master/protokol', {
            preserveScroll: true,
            onSuccess: () => {
                showFormModal.value = false;
                form.reset();
            },
        });
    }
};

// Toggle Active/Inactive Status
const togglingId = ref(null);
const toggleStatus = (p) => {
    togglingId.value = p.id;
    router.patch(
        `/master/protokol/${p.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                togglingId.value = null;
            },
        }
    );
};

// Stats Modal State
const showStatsModal = ref(false);
const loadingStats = ref(false);
const statData = ref(null);

const openStatsModal = async (p) => {
    showStatsModal.value = true;
    loadingStats.value = true;
    statData.value = null;
    try {
        const response = await window.axios.get(`/master/protokol/${p.id}/stats`, {
            params: { month: selectedMonth.value, year: selectedYear.value },
        });
        statData.value = response.data;
    } catch (e) {
        console.error('Gagal mengambil statistik protokol', e);
    } finally {
        loadingStats.value = false;
    }
};

// Delete Confirmation Modal State
const showDeleteModal = ref(false);
const deletingProtokol = ref(null);
const deleteForm = useForm({});

const confirmDelete = (p) => {
    deletingProtokol.value = p;
    showDeleteModal.value = true;
};

const deleteProtokol = () => {
    if (!deletingProtokol.value) return;
    deleteForm.delete(`/master/protokol/${deletingProtokol.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingProtokol.value = null;
        },
    });
};
</script>

<template>
    <Head title="Master Pegawai Protokol" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header & Action Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Master Pegawai Protokol</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola daftar personil protokol aktif dalam perhitungan algoritma penjadwalan.</p>
                    </div>

                    <!-- Filter & Action Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Search Input -->
                        <div class="relative">
                            <input
                                type="text"
                                v-model="search"
                                placeholder="Cari nama, NIP, atau WA..."
                                class="pl-8 pr-3 py-1.5 text-xs border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] w-48 sm:w-56"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <!-- Status Filter -->
                        <select
                            v-model="statusFilter"
                            class="px-3 py-1.5 text-xs border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-slate-700"
                        >
                            <option value="all">Semua Status</option>
                            <option value="active">Aktif Saja</option>
                            <option value="inactive">Nonaktif Saja</option>
                        </select>

                        <!-- Add Button -->
                        <button
                            type="button"
                            @click="openCreateModal"
                            class="px-3.5 py-1.5 bg-[#1A5276] hover:bg-blue-800 active:bg-blue-900 text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center space-x-1.5 shrink-0 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Tambah Protokol</span>
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Nama Pegawai</th>
                                <th class="px-4 py-3">No. WhatsApp</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Total Tugas (Bulan Ini)</th>
                                <th class="px-4 py-3 text-center">Dibayar vs Tidak</th>
                                <th class="px-4 py-3 text-center">Weekend</th>
                                <th class="px-4 py-3 text-center">Luar Kota</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="p in protokols.data" :key="p.id" class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0">
                                            {{ p.name ? p.name.charAt(0).toUpperCase() : 'P' }}
                                        </div>
                                        <div>
                                            <span class="font-semibold text-slate-900 block">{{ p.name }}</span>
                                            <span v-if="p.nip" class="text-[10px] text-slate-400">NIP. {{ p.nip }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 font-mono text-[11px]">{{ p.phone_wa }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="toggleStatus(p)"
                                        :disabled="togglingId === p.id"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold border transition cursor-pointer"
                                        :class="[
                                            (typeof p.status === 'object' ? p.status.value : p.status) === 'active'
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                                : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100',
                                            togglingId === p.id ? 'opacity-50 cursor-wait' : ''
                                        ]"
                                        title="Klik untuk ubah status"
                                    >
                                        {{ (typeof p.status === 'object' ? p.status.value : p.status) === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-slate-900">
                                    {{ p.total_tugas_bulan_ini ?? 0 }}x
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-emerald-700 font-semibold">{{ (p.tugas_dibayar_workday || 0) + (p.tugas_dibayar_offday || 0) }}x</span>
                                    /
                                    <span class="text-slate-500">{{ p.tugas_unpaid || 0 }}x</span>
                                </td>
                                <td class="px-4 py-3 text-center font-medium" :class="(p.tugas_dibayar_offday || 0) > 1 ? 'text-amber-700 font-bold' : 'text-slate-600'">
                                    {{ p.tugas_dibayar_offday || 0 }}x
                                </td>
                                <td class="px-4 py-3 text-center font-medium text-slate-600">
                                    {{ p.trip_luar_kota || 0 }}x
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end space-x-1.5">
                                        <!-- Stats Button -->
                                        <button
                                            type="button"
                                            @click="openStatsModal(p)"
                                            class="p-1 rounded-md text-blue-700 hover:bg-blue-50 transition"
                                            title="Lihat Statistik Beban Kerja"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </button>

                                        <!-- Edit Button -->
                                        <button
                                            type="button"
                                            @click="openEditModal(p)"
                                            class="p-1 rounded-md text-slate-500 hover:bg-slate-100 transition"
                                            title="Ubah Data"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Delete Button -->
                                        <button
                                            type="button"
                                            @click="confirmDelete(p)"
                                            class="p-1 rounded-md text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                            title="Hapus / Arsipkan"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!protokols.data || protokols.data.length === 0">
                                <td colspan="8" class="text-center py-10 text-slate-400">
                                    Tidak ada data pegawai protokol yang sesuai dengan kriteria pencarian.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <div v-if="protokols.links && protokols.links.length > 3" class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <div>
                        Menampilkan {{ protokols.from || 0 }} sampai {{ protokols.to || 0 }} dari total {{ protokols.total || 0 }} pegawai
                    </div>
                    <div class="flex items-center space-x-1">
                        <Link
                            v-for="(link, i) in protokols.links"
                            :key="i"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="px-2.5 py-1 rounded-md border text-xs transition"
                            :class="[
                                link.active ? 'bg-[#1A5276] text-white border-[#1A5276] font-bold' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                            preserve-scroll
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL FORM CREATE / EDIT -->
        <Modal :show="showFormModal" @close="showFormModal = false" maxWidth="md">
            <div class="p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ isEditing ? 'Ubah Data Protokol' : 'Tambah Protokol Baru' }}
                    </h3>
                    <button
                        type="button"
                        @click="showFormModal = false"
                        class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="saveProtokol" class="space-y-4 mt-4 text-xs">
                    <div>
                        <InputLabel value="Nama Lengkap & Gelar *" />
                        <TextInput
                            v-model="form.name"
                            type="text"
                            class="w-full"
                            placeholder="Contoh: Rahmat Hidayat, S.Sos."
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="No. WhatsApp *" />
                            <TextInput
                                v-model="form.phone_wa"
                                type="text"
                                class="w-full"
                                placeholder="081234567890"
                                required
                            />
                            <InputError :message="form.errors.phone_wa" />
                        </div>
                        <div>
                            <InputLabel value="NIP (Opsional)" />
                            <TextInput
                                v-model="form.nip"
                                type="text"
                                class="w-full"
                                placeholder="1985..."
                            />
                            <InputError :message="form.errors.nip" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Status Ketersediaan" />
                        <select
                            v-model="form.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white text-xs"
                        >
                            <option value="active">Aktif (Masuk Pool Jadwal)</option>
                            <option value="inactive">Nonaktif (Cuti / Tugas Lain)</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div>
                        <InputLabel value="Catatan Tambahan" />
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] text-xs"
                            placeholder="Catatan keahlian, tugas khusus, dll."
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                        <SecondaryButton @click="showFormModal = false" :disabled="form.processing">
                            Batal
                        </SecondaryButton>
                        <PrimaryButton :loading="form.processing">
                            Simpan Data
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL STATISTIK INDIVIDU -->
        <Modal :show="showStatsModal" @close="showStatsModal = false" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Statistik Penugasan Pegawai</span>
                        <h3 class="text-base font-bold text-slate-900 mt-0.5">{{ statData?.protokol?.name || 'Memuat data...' }}</h3>
                        <p v-if="statData?.protokol" class="text-xs text-slate-500">
                            {{ statData.protokol.phone_wa }} • Status {{ (typeof statData.protokol.status === 'object' ? statData.protokol.status.value : statData.protokol.status) === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showStatsModal = false"
                        class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold"
                    >
                        &times;
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loadingStats" class="py-12 flex flex-col items-center justify-center text-slate-400 space-y-2">
                    <svg class="animate-spin h-6 w-6 text-[#1A5276]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span class="text-xs">Mengambil statistik beban kerja...</span>
                </div>

                <!-- Content -->
                <div v-else-if="statData?.protokol" class="space-y-4 my-4">
                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                            <span class="text-[10px] text-slate-400 block font-medium">Total</span>
                            <span class="text-lg font-bold text-slate-900">{{ statData.protokol.total_tugas_bulan_ini || 0 }}x</span>
                        </div>
                        <div class="p-2.5 bg-emerald-50 rounded-xl border border-emerald-100">
                            <span class="text-[10px] text-emerald-700 block font-medium">Dibayar</span>
                            <span class="text-lg font-bold text-emerald-700">
                                {{ (statData.protokol.tugas_dibayar_workday || 0) + (statData.protokol.tugas_dibayar_offday || 0) }}x
                            </span>
                        </div>
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                            <span class="text-[10px] text-slate-500 block font-medium">Tidak Dibayar</span>
                            <span class="text-lg font-bold text-slate-700">{{ statData.protokol.tugas_unpaid || 0 }}x</span>
                        </div>
                        <div class="p-2.5 bg-amber-50 rounded-xl border border-amber-100">
                            <span class="text-[10px] text-amber-700 block font-medium">Weekend</span>
                            <span class="text-lg font-bold text-amber-700">{{ statData.protokol.tugas_dibayar_offday || 0 }}x</span>
                        </div>
                    </div>

                    <!-- History -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-900 mb-2">Riwayat Penugasan Terbaru</h4>
                        <div class="space-y-2 text-xs max-h-56 overflow-y-auto">
                            <div
                                v-for="(task, idx) in statData.history"
                                :key="idx"
                                class="p-2.5 bg-slate-50 rounded-lg flex items-center justify-between border border-slate-100"
                            >
                                <div>
                                    <span class="font-semibold block text-slate-800">{{ task.kegiatan?.title || 'Kegiatan' }}</span>
                                    <span class="text-[11px] text-slate-500">{{ task.assigned_at ? new Date(task.assigned_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-' }}</span>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold"
                                    :class="task.kegiatan?.location_type === 'luar_kota' ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ task.kegiatan?.location_type === 'luar_kota' ? 'Luar Kota' : 'Dalam Kota' }}
                                </span>
                            </div>
                            <p v-if="!statData.history || statData.history.length === 0" class="text-slate-400 text-center py-4">
                                Belum ada riwayat penugasan.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showStatsModal = false">
                        Tutup
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL KONFIRMASI HAPUS PROTOKOL -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" maxWidth="md">
            <div class="p-6">
                <div class="flex items-center space-x-3 text-rose-600 mb-4">
                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Pegawai</h3>
                        <p class="text-xs text-slate-500">Tindakan ini akan mengarsipkan data personil secara aman.</p>
                    </div>
                </div>

                <div v-if="deletingProtokol" class="bg-slate-50 p-3.5 rounded-xl border border-slate-100 text-xs text-slate-700 space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nama Personil:</span>
                        <span class="font-bold text-slate-900">{{ deletingProtokol.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Riwayat Penugasan:</span>
                        <span class="font-semibold text-slate-900">{{ deletingProtokol.total_tugas_bulan_ini || 0 }} kegiatan bulan ini</span>
                    </div>
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] mb-4 flex items-start space-x-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Pegawai akan diarsipkan (soft delete) sehingga tidak muncul lagi di pool penjadwalan baru, namun seluruh arsip riwayat dinas dan laporan masa lalu tetap terlindungi secara permanen.</span>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <SecondaryButton @click="showDeleteModal = false" :disabled="deleteForm.processing">
                        Batal
                    </SecondaryButton>
                    <DangerButton @click="deleteProtokol" :loading="deleteForm.processing">
                        Ya, Hapus Personil
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
