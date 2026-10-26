<script setup>
import { ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    protokols: {
        type: Array,
        required: true,
    },
    teamFairness: {
        type: Object,
        default: () => null,
    },
    selectedMonth: {
        type: Number,
        required: true,
    },
    selectedYear: {
        type: Number,
        required: true,
    },
    monthName: {
        type: String,
        required: true,
    },
});

const month = ref(props.selectedMonth);
const year = ref(props.selectedYear);

watch([month, year], ([newMonth, newYear]) => {
    router.get(
        "/laporan/distribusi",
        { month: newMonth, year: newYear },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const formatKunkerDate = (dateStr) => {
    if (!dateStr) return "";
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, "0");
    const m = String(d.getMonth() + 1).padStart(2, "0");
    return `${day}/${m}`;
};

const monthOptions = [
    { value: 1, label: "Januari" },
    { value: 2, label: "Februari" },
    { value: 3, label: "Maret" },
    { value: 4, label: "April" },
    { value: 5, label: "Mei" },
    { value: 6, label: "Juni" },
    { value: 7, label: "Juli" },
    { value: 8, label: "Agustus" },
    { value: 9, label: "September" },
    { value: 10, label: "Oktober" },
    { value: 11, label: "November" },
    { value: 12, label: "Desember" },
];
</script>

<template>
    <Head title="Rekapitulasi Keseimbangan Tugas" />

    <AppLayout>
        <div class="space-y-6">
            <div
                class="bg-white rounded-xl border border-slate-200 shadow-xs p-6"
            >
                <!-- Filter and Export Bar -->
                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-slate-100 gap-4"
                >
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Rekapitulasi Distribusi Tugas Protokol
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Evaluasi 7 metrik kedinasan: honorarium, kunjungan
                            kerja, piket WFA, rotasi libur, dan status
                            keseimbangan distribusi (Periode: {{ monthName }}
                            {{ year }}).
                        </p>
                    </div>

                    <!-- Month Filter & Export Buttons -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div
                            class="flex items-center space-x-1 bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs"
                        >
                            <select
                                v-model="month"
                                class="px-2.5 py-1 bg-white border border-slate-200 rounded text-xs font-semibold outline-none text-slate-700"
                            >
                                <option
                                    v-for="m in monthOptions"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </option>
                            </select>

                            <select
                                v-model="year"
                                class="px-2.5 py-1 bg-white border border-slate-200 rounded text-xs font-semibold outline-none text-slate-700"
                            >
                                <option :value="2024">2024</option>
                                <option :value="2025">2025</option>
                                <option :value="2026">2026</option>
                                <option :value="2027">2027</option>
                            </select>
                        </div>

                        <!-- Export Excel -->
                        <a
                            :href="`/laporan/export/excel?month=${month}&year=${year}`"
                            target="_blank"
                            class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold rounded-lg shadow-2xs transition"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <span>Ekspor Excel (.xlsx)</span>
                        </a>

                        <!-- Export PDF -->
                        <a
                            :href="`/laporan/export/pdf?month=${month}&year=${year}`"
                            target="_blank"
                            class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-xs font-semibold rounded-lg shadow-2xs transition"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                />
                            </svg>
                            <span>Cetak PDF</span>
                        </a>
                    </div>
                </div>

                <!-- Card Status Keseimbangan Tim (PRD v2.0 Fitur 7) -->
                <div
                    v-if="teamFairness"
                    class="mt-6 p-4 rounded-xl border flex flex-col md:flex-row md:items-center md:justify-between gap-4 transition-all"
                    :class="[
                        teamFairness.status === 'sangat_seimbang' ? 'bg-emerald-50/70 border-emerald-200' :
                        teamFairness.status === 'cukup_seimbang' ? 'bg-amber-50/70 border-amber-200' :
                        'bg-rose-50/70 border-rose-200'
                    ]"
                >
                    <div class="flex items-start space-x-3.5">
                        <div
                            class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 shadow-2xs font-extrabold text-sm"
                            :class="[
                                teamFairness.status === 'sangat_seimbang' ? 'bg-emerald-600 text-white' :
                                teamFairness.status === 'cukup_seimbang' ? 'bg-amber-500 text-white' :
                                'bg-rose-600 text-white'
                            ]"
                        >
                            {{ teamFairness.indeks }}%
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Status Keseimbangan Tim:
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-extrabold border"
                                    :class="teamFairness.badge_class"
                                >
                                    {{ teamFairness.status_label }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-700 mt-1 font-medium">
                                {{ teamFairness.diagnosa_text }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 text-xs">
                        <span class="px-2.5 py-1 rounded-lg bg-white border border-rose-200 text-rose-700 font-bold shadow-2xs flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                            <span>{{ teamFairness.counts?.overwork || 0 }} Overwork</span>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-white border border-emerald-200 text-emerald-700 font-bold shadow-2xs flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                            <span>{{ teamFairness.counts?.seimbang || 0 }} Seimbang</span>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-white border border-amber-200 text-amber-700 font-bold shadow-2xs flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                            <span>{{ teamFairness.counts?.harus_ditugaskan || 0 }} Ditugaskan</span>
                        </span>
                    </div>
                </div>

                <!-- 7-Metric Standard Table (PRD v2.0 Compliant - Tanpa NIP) -->
                <div class="overflow-x-auto mt-4">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-slate-50 text-slate-700 uppercase font-bold border-b border-slate-200"
                        >
                            <tr>
                                <th class="px-3 py-3 w-10 text-center">No</th>
                                <th class="px-4 py-3 min-w-[180px]">
                                    Nama Protokol
                                </th>
                                <th class="px-3 py-3 text-center">
                                    Tidak Dibayar
                                </th>
                                <th class="px-3 py-3 text-center">Dibayar</th>
                                <th class="px-3 py-3 text-center">
                                    Kemungkinan Dibayar
                                </th>
                                <th class="px-4 py-3 min-w-[150px]">Kunker</th>
                                <th class="px-3 py-3 text-center">WFA (Jum)</th>
                                <th class="px-3 py-3 text-center">
                                    Hari Weekend
                                </th>
                                <th class="px-3 py-3 text-center">
                                    Hari Weekday
                                </th>
                                <th class="px-3 py-3 text-center font-black">
                                    Total Tugas
                                </th>
                                <th class="px-3 py-3 text-center">
                                    Status Beban Kerja
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(p, idx) in protokols"
                                :key="p.id"
                                class="hover:bg-slate-50 transition"
                            >
                                <td
                                    class="px-3 py-3.5 text-center text-slate-500 font-medium"
                                >
                                    {{ idx + 1 }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900">
                                        {{ p.name }}
                                    </div>
                                    <div
                                        class="text-[11px] text-slate-500 font-normal"
                                    >
                                        {{ p.phone_wa }}
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]"
                                    >
                                        {{ p.tugas_unpaid || 0 }}x
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[11px]"
                                    >
                                        {{
                                            p.tugas_paid ??
                                            (p.tugas_dibayar_workday || 0) +
                                                (p.tugas_dibayar_offday || 0)
                                        }}x
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 font-bold text-[11px]"
                                    >
                                        {{ p.tugas_conditional || 0 }}x
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center space-x-1.5">
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-violet-100 text-violet-800 font-bold text-[11px]"
                                        >
                                            {{ p.tugas_kunker || 0 }}x
                                        </span>
                                    </div>
                                    <ul
                                        v-if="
                                            p.kegiatan && p.kegiatan.length > 0
                                        "
                                        class="mt-1.5 space-y-0.5 text-[11px] text-slate-600"
                                    >
                                        <li
                                            v-for="k in p.kegiatan"
                                            :key="k.id"
                                            class="flex items-start space-x-1"
                                        >
                                            <span
                                                class="text-violet-500 font-bold"
                                                >•</span
                                            >
                                            <span
                                                ><strong
                                                    >{{
                                                        formatKunkerDate(
                                                            k.start_datetime,
                                                        )
                                                    }}:</strong
                                                >
                                                {{ k.title }}</span
                                            >
                                        </li>
                                    </ul>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded font-bold text-[11px]"
                                        :class="
                                            (p.tugas_wfa || 0) > 0
                                                ? 'bg-sky-100 text-sky-800'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{ p.tugas_wfa || 0 }}x
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded font-bold text-[11px]"
                                        :class="
                                            (p.tugas_offday || 0) > 0
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{ p.tugas_offday || 0 }}x
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-3.5 text-center text-slate-700 font-semibold"
                                >
                                    {{ p.tugas_workday || 0 }}x
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <span
                                        class="px-2.5 py-1 rounded-lg bg-slate-900 text-white font-extrabold text-xs shadow-2xs"
                                    >
                                        {{ p.total_tugas_bulan_ini || 0 }}x
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <span
                                            class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full font-bold text-[11px] border whitespace-nowrap shadow-2xs"
                                            :class="
                                                p.level_beban?.class ||
                                                p.status_keseimbangan?.class ||
                                                'bg-slate-100 text-slate-700 border-slate-200'
                                            "
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full shrink-0"
                                                :class="p.level_beban?.dot_color || 'bg-slate-400'"
                                            ></span>
                                            <span>{{
                                                p.level_beban?.label ||
                                                p.status_keseimbangan?.label ||
                                                "Seimbang"
                                            }}</span>
                                        </span>
                                        <span
                                            v-if="p.level_beban?.diagnosa && p.level_beban?.level !== 'seimbang'"
                                            class="text-[10px] text-slate-500 mt-1 max-w-[130px] leading-tight text-center"
                                            :title="p.level_beban?.diagnosa"
                                        >
                                            {{ p.level_beban?.diagnosa }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!protokols || protokols.length === 0">
                                <td
                                    colspan="11"
                                    class="text-center py-10 text-slate-400"
                                >
                                    Belum ada data protokol aktif pada periode
                                    ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
