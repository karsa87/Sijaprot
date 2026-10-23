<div class="space-y-6">

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6">
        <!-- Filter and Export Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-slate-100 gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Rekapitulasi Keseimbangan Tugas Protokol</h2>
                <p class="text-xs text-slate-500 mt-0.5">Monitoring keadilan pembagian kegiatan dibayar, dinas luar kota,
                    dan perlindungan hari libur.</p>
            </div>

            <!-- Month Filter & Export Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex items-center space-x-1 bg-slate-50 border border-slate-200 rounded-lg p-1 text-xs">
                    <select wire:model.live="selectedMonth"
                        class="px-2 py-1 bg-white border border-slate-200 rounded text-xs font-semibold outline-none">
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">
                                {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>

                    <select wire:model.live="selectedYear"
                        class="px-2 py-1 bg-white border border-slate-200 rounded text-xs font-semibold outline-none">
                        <option value="2025">2025</option>
                        <option value="2026">2026</option>
                        <option value="2027">2027</option>
                    </select>
                </div>

                <a href="{{ route('laporan.export.excel', ['month' => $selectedMonth, 'year' => $selectedYear]) }}"
                    class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg shadow-xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <span>Export Excel (.xlsx)</span>
                </a>

                <a href="{{ route('laporan.export.pdf', ['month' => $selectedMonth, 'year' => $selectedYear]) }}"
                    class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-lg shadow-xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                        </path>
                    </svg>
                    <span>Export PDF</span>
                </a>
            </div>
        </div>

        <!-- Balance Metrics Table -->
        <div class="overflow-x-auto mt-4">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 uppercase font-semibold border-b border-slate-200">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 align-middle">Nama Pegawai Protokol</th>
                        <th colspan="2" class="px-4 py-2 text-center border-b border-slate-200">Dibayar</th>
                        <th colspan="2" class="px-4 py-2 text-center border-b border-slate-200">Tidak Dibayar</th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">Total Weekday</th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">Total Weekend</th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">Dalam Kota</th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">Luar Kota</th>
                        <th rowspan="2" class="px-4 py-3 text-center font-bold align-middle">Total Bertugas</th>
                        <th rowspan="2" class="px-4 py-3 text-center align-middle">Status Fairness</th>
                    </tr>
                    <tr>
                        <th
                            class="px-3 py-2 text-center text-[10px] font-medium text-slate-500 border-r border-slate-200/50">
                            Hari Kerja</th>
                        <th class="px-3 py-2 text-center text-[10px] font-medium text-slate-500">Hari Libur</th>
                        <th
                            class="px-3 py-2 text-center text-[10px] font-medium text-slate-500 border-r border-slate-200/50">
                            Hari Kerja</th>
                        <th class="px-3 py-2 text-center text-[10px] font-medium text-slate-500">Hari Libur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($protokols as $p)
                        @php
                            $total = $p->total_tugas_bulan_ini;
                            $paid = $p->tugas_dibayar_workday + $p->tugas_dibayar_offday;
                            $statusClass = 'bg-emerald-100 text-emerald-800';
                            $statusLabel = 'Seimbang';

                            if ($total == 0) {
                                $statusClass = 'bg-amber-100 text-amber-800';
                                $statusLabel = 'Belum Bertugas';
                            } elseif ($p->tugas_dibayar_offday == 0) {
                                $statusClass = 'bg-blue-100 text-blue-800';
                                $statusLabel = 'Prioritas Weekend';
                            } elseif ($paid >= 3) {
                                $statusClass = 'bg-emerald-100 text-emerald-800';
                                $statusLabel = 'Sangat Seimbang';
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                <div>
                                    <span>{{ $p->name }}</span>
                                    <span
                                        class="text-[10px] text-slate-400 block font-normal">{{ $p->phone_wa }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center text-emerald-700 font-semibold">
                                {{ $p->tugas_dibayar_workday }}x</td>
                            <td
                                class="px-3 py-3 text-center font-medium {{ $p->tugas_dibayar_offday > 1 ? 'text-amber-700' : 'text-slate-600' }}">
                                {{ $p->tugas_dibayar_offday }}x</td>
                            <td class="px-3 py-3 text-center text-slate-600">{{ $p->tugas_unpaid_workday }}x</td>
                            <td class="px-3 py-3 text-center text-slate-600">{{ $p->tugas_unpaid_offday }}x</td>
                            <td class="px-4 py-3 text-center font-bold text-slate-800">{{ $p->tugas_workday }}x</td>
                            <td class="px-4 py-3 text-center font-bold text-slate-800">{{ $p->tugas_offday }}x</td>
                            <td class="px-4 py-3 text-center text-slate-600">{{ $p->trip_dalam_kota }}x</td>
                            <td class="px-4 py-3 text-center font-medium text-slate-600">{{ $p->trip_luar_kota }}x</td>
                            <td class="px-4 py-3 text-center font-bold text-slate-900 bg-slate-50/50">
                                {{ $total }}x</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-8 text-slate-400">
                                Belum ada data protokol aktif.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
