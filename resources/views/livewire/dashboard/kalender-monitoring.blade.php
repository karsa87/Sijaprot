<div class="space-y-6">

    <!-- Top Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Kegiatan Bulan Ini</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1">{{ $totalKegiatanBulanIni }} <span class="text-xs font-normal text-slate-500">kegiatan</span></h3>
                <p class="text-[11px] text-emerald-600 mt-1 flex items-center font-medium">
                    <svg class="w-3.5 h-3.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Terjadwal di Sistem
                </p>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-[#1A5276] rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Protokol Siap Tugas</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1">{{ $totalProtokolAktif }} <span class="text-xs font-normal text-slate-500">/ {{ $totalProtokol }} pegawai</span></h3>
                <p class="text-[11px] text-blue-600 mt-1">Status pool aktif ✓</p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Indeks Keseimbangan</p>
                <h3 class="text-2xl font-bold text-emerald-600 mt-1">{{ $indeksKeseimbangan }}% <span class="text-xs font-normal text-slate-500">Optimal</span></h3>
                <p class="text-[11px] text-slate-500 mt-1">Distribusi tugas adil & seimbang</p>
            </div>
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Hak Libur Terlindungi</p>
                <h3 class="text-2xl font-bold text-slate-900 mt-1">100%</h3>
                <p class="text-[11px] text-slate-500 mt-1">Aturan H3 Full Weekend Aktif</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                        <h2 class="text-lg font-bold text-slate-900">{{ $monthName }} {{ $year }}</h2>
                        @if ($month == Carbon\Carbon::now()->month && $year == Carbon\Carbon::now()->year)
                            <span class="text-xs font-medium bg-blue-100 text-[#1A5276] px-2 py-0.5 rounded-md">Bulan Berjalan</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Klik tanggal untuk memunculkan detail kegiatan dan protokol bertugas.</p>
                </div>

                <div class="flex items-center space-x-2">
                    <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200 text-xs font-semibold">
                        <button wire:click="prevMonth" class="px-2.5 py-1 text-slate-600 hover:text-slate-900 rounded hover:bg-white transition">&lt; Prev</button>
                        <button wire:click="goToCurrentMonth" class="px-2.5 py-1 text-blue-900 bg-white shadow-xs rounded">Bulan Ini</button>
                        <button wire:click="nextMonth" class="px-2.5 py-1 text-slate-600 hover:text-slate-900 rounded hover:bg-white transition">Next &gt;</button>
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
                @for ($i = 0; $i < $startOffset; $i++)
                    <div class="h-20 bg-slate-50/40 rounded-lg border border-slate-100 p-1 opacity-30"></div>
                @endfor

                <!-- Days of Month -->
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dayOfWeekIndex = ($startOffset + $day - 1) % 7; // 5=Sabtu, 6=Minggu
                        $isWeekend = ($dayOfWeekIndex === 5 || $dayOfWeekIndex === 6);
                        $hasEvents = !empty($eventsByDay[$day]);
                        $isSelected = ($day === $selectedDay);
                        $hasHoliday = isset($holidays[$day]);
                    @endphp

                    <div wire:click="selectDate({{ $day }})"
                         class="h-20 rounded-lg p-1.5 cursor-pointer transition border text-left flex flex-col justify-between relative
                         {{ $isSelected
                             ? 'ring-2 ring-[#1A5276] bg-blue-50/70 border-blue-400 font-bold shadow-xs'
                             : ($isWeekend || $hasHoliday
                                 ? 'bg-rose-50/30 border-rose-100 hover:bg-rose-50/70'
                                 : 'bg-white border-slate-200 hover:bg-slate-50') }}">

                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold {{ ($isWeekend || $hasHoliday) ? 'text-rose-600' : 'text-slate-800' }} {{ $isSelected ? 'w-5 h-5 rounded-full bg-[#1A5276] text-white flex items-center justify-center font-bold text-[11px]' : '' }}">
                                {{ $day }}
                            </span>

                            @if ($hasHoliday)
                                <span class="text-[8px] bg-rose-500 text-white px-1 py-0.2 rounded font-bold" title="{{ $holidays[$day]->name }}">
                                    LIBUR
                                </span>
                            @endif
                        </div>

                        <!-- Dots / Snippets Kegiatan -->
                        @if ($hasEvents)
                            <div class="space-y-0.5 mt-0.5 overflow-hidden">
                                @foreach (array_slice($eventsByDay[$day], 0, 2) as $ev)
                                    @php
                                        $badgeColor = 'bg-emerald-500 text-white';
                                        if (!$ev->jenisKegiatan?->isPaid()) {
                                            $badgeColor = 'bg-slate-300 text-slate-700';
                                        } elseif ($ev->isLuarKota()) {
                                            $badgeColor = 'bg-purple-600 text-white';
                                        } elseif ($ev->is_weekend || $ev->is_holiday) {
                                            $badgeColor = 'bg-amber-500 text-white';
                                        }
                                    @endphp
                                    <div class="text-[9px] truncate px-1 py-0.2 rounded {{ $badgeColor }}" title="{{ $ev->title }}">
                                        {{ $ev->title }}
                                    </div>
                                @endforeach

                                @if (count($eventsByDay[$day]) > 2)
                                    <div class="text-[9px] text-slate-400 font-normal">
                                        +{{ count($eventsByDay[$day]) - 2 }} lainnya
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endfor
            </div>

            <!-- Legend Bar -->
            <div class="flex flex-wrap items-center gap-4 mt-5 pt-3 border-t border-slate-100 text-xs text-slate-600">
                <div class="flex items-center space-x-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500"></span><span>Dibayar (Hari Kerja)</span></div>
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
                            {{ $selectedDateObj->translatedFormat('l, d F Y') }}
                        </h3>
                    </div>

                    @if ($selectedHoliday)
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800" title="{{ $selectedHoliday->name }}">
                            {{ $selectedHoliday->name }}
                        </span>
                    @elseif ($isSelectedWeekend)
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                            Hari Libur Weekend
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                            Hari Kerja
                        </span>
                    @endif
                </div>

                <!-- List Kegiatan Pada Tanggal Terpilih -->
                <div class="space-y-3 mt-4 max-h-[460px] overflow-y-auto">
                    @forelse ($selectedEvents as $kegiatan)
                        @php
                            $badgeTypeColor = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                            if (!$kegiatan->jenisKegiatan?->isPaid()) {
                                $badgeTypeColor = 'bg-slate-100 text-slate-700 border-slate-200';
                            } elseif ($kegiatan->isLuarKota()) {
                                $badgeTypeColor = 'bg-purple-100 text-purple-800 border-purple-200';
                            } elseif ($kegiatan->is_weekend || $kegiatan->is_holiday) {
                                $badgeTypeColor = 'bg-amber-100 text-amber-800 border-amber-200';
                            }
                        @endphp

                        <div class="p-3.5 bg-slate-50 hover:bg-slate-100/70 rounded-xl border border-slate-200 transition space-y-2">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="text-[11px] font-semibold text-slate-500">
                                        {{ $kegiatan->start_datetime->format('H:i') }} - {{ $kegiatan->end_datetime->format('H:i') }} WIB
                                    </span>
                                    <h4 class="font-bold text-slate-900 text-xs mt-0.5">{{ $kegiatan->title }}</h4>
                                </div>
                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border {{ $badgeTypeColor }}">
                                        {{ $kegiatan->jenisKegiatan?->payment_type?->label() ?? 'Free' }} • {{ $kegiatan->location_type->label() }}
                                    </span>
                                    <a href="{{ route('kegiatan.edit', $kegiatan->id) }}" class="p-1 rounded text-slate-400 hover:text-blue-700 hover:bg-slate-200 transition" title="Ubah Kegiatan">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <button wire:click="confirmDeleteKegiatan({{ $kegiatan->id }})" class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Hapus Kegiatan">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>

                            @if ($kegiatan->detail)
                                <p class="text-[11px] text-slate-600 line-clamp-2">{{ $kegiatan->detail }}</p>
                            @endif

                            <div class="pt-2 border-t border-slate-200/60">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">
                                    Petugas Protokol ({{ $kegiatan->protokols->count() }} orang)
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($kegiatan->protokols as $proto)
                                        <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full bg-white border border-slate-200 text-xs font-semibold text-slate-700 shadow-2xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ $proto->name }}</span>
                                        </span>
                                    @empty
                                        <span class="text-rose-500 text-[11px]">Belum ada protokol ditugaskan</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-slate-400">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-xs font-medium">Tidak ada kegiatan di tanggal ini.</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Semua personil bebas penugasan.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Action Button -->
            <div class="mt-6 pt-4 border-t border-slate-100">
                <button wire:click="scheduleOnSelectedDate"
                        class="w-full py-2.5 px-3 rounded-lg bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold flex items-center justify-center space-x-1.5 shadow-xs transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>+ Jadwalkan Kegiatan Pada Tanggal Ini</span>
                </button>
            </div>
        </div>

    </div>

    <!-- MODAL KONFIRMASI HAPUS KEGIATAN -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center space-x-3 text-rose-600 mb-4">
                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Kegiatan</h3>
                        <p class="text-xs text-slate-500">Jadwal dan seluruh penugasan personil akan dibatalkan.</p>
                    </div>
                </div>

                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-100 text-xs text-slate-700 space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nama Acara:</span>
                        <span class="font-bold text-slate-900 text-right">{{ $deletingKegiatanTitle }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Waktu Pelaksanaan:</span>
                        <span class="font-semibold text-slate-900">{{ $deletingKegiatanTime }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Personil Ditugaskan:</span>
                        <span class="font-semibold text-slate-900">{{ $deletingKegiatanProtokolCount }} orang personil</span>
                    </div>
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] mb-4 flex items-start space-x-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Penghapusan ini akan membebaskan kembali jadwal seluruh personil protokol terkait sehingga siap untuk ditugaskan pada agenda lain.</span>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button wire:click="cancelDeleteKegiatan" type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                        Batal
                    </button>
                    <button wire:click="deleteKegiatan" type="button" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center space-x-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        <span>Ya, Hapus Kegiatan</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
