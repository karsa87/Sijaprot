<div class="space-y-6">

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-900">Form Buat Kegiatan & Engine Penjadwalan Otomatis</h2>
                <p class="text-xs text-slate-500 mt-0.5">Sistem secara instan menyeleksi kandidat protokol teradil berdasarkan 4 Hard Rules & 4 Soft Rules.</p>
            </div>
            <span class="bg-blue-100 text-[#1A5276] text-xs font-semibold px-2.5 py-1 rounded-full shrink-0">
                Livewire Reactive Engine
            </span>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- LEFT 7 COLS: FORM INPUT -->
            <div class="lg:col-span-7 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Judul / Nama Kegiatan <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="title" placeholder="Contoh: Penyambutan Kunjungan Kerja Tamu Negara"
                           class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1A5276] focus:border-[#1A5276] outline-none transition">
                    @error('title') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Template Jenis Kegiatan <span class="text-rose-500">*</span></label>
                        <select wire:model.live="jenis_kegiatan_id"
                                class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1A5276] focus:border-[#1A5276] bg-white outline-none">
                            @foreach ($jenisList as $j)
                                <option value="{{ $j->id }}">{{ $j->name }} ({{ $j->location_type->label() }})</option>
                            @endforeach
                        </select>
                        @error('jenis_kegiatan_id') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jumlah Protokol Dibutuhkan <span class="text-rose-500">*</span></label>
                        <div class="flex items-center space-x-2">
                            <input type="number" wire:model="jumlah_protokol" min="1" max="10"
                                   class="w-24 px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1A5276] outline-none font-bold text-center">
                            <span class="text-xs text-slate-500">orang personil</span>
                        </div>
                        @error('jumlah_protokol') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Tanggal & Jam Mulai / Selesai -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-700 uppercase block mb-1">Waktu Mulai</span>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" wire:model.live="start_date" class="px-2 py-1.5 text-xs border border-slate-300 rounded bg-white">
                            <input type="time" wire:model="start_time" class="px-2 py-1.5 text-xs border border-slate-300 rounded bg-white">
                        </div>
                        @error('start_date') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-700 uppercase block mb-1">Waktu Selesai</span>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" wire:model="end_date" class="px-2 py-1.5 text-xs border border-slate-300 rounded bg-white">
                            <input type="time" wire:model="end_time" class="px-2 py-1.5 text-xs border border-slate-300 rounded bg-white">
                        </div>
                        @error('end_date') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Auto-detected status bar -->
                <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl space-y-2">
                    <span class="text-[11px] font-bold text-[#1A5276] uppercase tracking-wider block">Klasifikasi Otomatis Terdeteksi Sistem</span>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                            <span class="text-[10px] text-slate-400 block">Kategori Hari</span>
                            <span class="font-bold {{ ($isWeekend || $isHoliday) ? 'text-rose-600' : 'text-slate-700' }}">
                                {{ $detectedHari }}
                            </span>
                        </div>
                        <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                            <span class="text-[10px] text-slate-400 block">Honorarium / Fee</span>
                            <span class="font-bold {{ str_contains($detectedFee, 'Luar') ? 'text-amber-600' : (str_contains($detectedFee, 'Tidak') ? 'text-slate-500' : 'text-emerald-600') }}">
                                {{ $detectedFee }}
                            </span>
                        </div>
                        <div class="p-2 bg-white rounded-lg border border-blue-100 shadow-2xs">
                            <span class="text-[10px] text-slate-400 block">Wilayah Dinas</span>
                            <span class="font-bold {{ str_contains($detectedLokasi, 'Luar') ? 'text-purple-700' : 'text-blue-700' }}">
                                {{ $detectedLokasi }}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Detail / Catatan Rundown Acara</label>
                    <textarea wire:model="detail" rows="2" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-[#1A5276] outline-none"
                              placeholder="Rundown, dress code (PSH/Batik), penjemputan, dsb."></textarea>
                </div>

                <button wire:click="runAutoSchedulerSimulation" wire:loading.attr="disabled"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-[#1A5276] to-blue-700 hover:from-blue-800 hover:to-blue-900 text-white font-bold text-xs flex items-center justify-center space-x-2 shadow-md transition transform active:scale-[0.99] disabled:opacity-50">
                    <svg wire:loading.remove class="w-4 h-4 text-yellow-300 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path></svg>
                    <svg wire:loading class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <span>Jalankan Algoritma Penjadwalan Otomatis</span>
                </button>
            </div>

            <!-- RIGHT 5 COLS: HASIL REKOMENDASI ENGINE -->
            <div class="lg:col-span-5 bg-slate-50 p-5 rounded-xl border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <span class="text-xs font-bold text-slate-900 flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full {{ $hasRunSimulation ? 'bg-emerald-500 animate-ping' : 'bg-slate-300' }}"></span>
                            <span>Hasil Rekomendasi Algoritma</span>
                        </span>
                        <span class="text-[11px] font-semibold {{ $hasRunSimulation ? 'text-emerald-700 bg-emerald-100' : 'text-slate-500 bg-slate-200' }} px-2 py-0.5 rounded">
                            {{ $hasRunSimulation ? 'Rekomendasi Siap' : 'Menunggu Simulasi' }}
                        </span>
                    </div>

                    @if ($relaxationWarning)
                        <div class="mt-3 p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800">
                            {{ $relaxationWarning }}
                        </div>
                    @endif

                    <!-- Candidates List -->
                    <div class="space-y-3 mt-4">
                        @if ($hasRunSimulation && count($recommendations) > 0)
                            @foreach ($recommendations as $idx => $rec)
                                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs space-y-1.5 transition hover:shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-5 h-5 rounded-full bg-[#1A5276] text-white flex items-center justify-center font-bold text-[10px]">
                                                #{{ $idx + 1 }}
                                            </span>
                                            <span class="font-bold text-slate-900 text-xs">{{ $rec['protokol']['name'] }}</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                            Skor: {{ number_format($rec['score'], 1) }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 leading-relaxed">{{ $rec['reason'] }}</p>
                                    <div class="flex items-center justify-between pt-1 text-[10px] text-slate-400">
                                        <span class="text-blue-700 font-semibold">{{ $rec['protokol']['phone_wa'] }}</span>
                                        <span>Hard Rules: ✅ Lolos</span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="py-12 text-center text-slate-400 text-xs">
                                <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span>Klik tombol "Jalankan Algoritma" untuk melihat rekomendasi personil.</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Actions: Confirm & Manual Override -->
                <div class="mt-6 pt-4 border-t border-slate-200 space-y-2">
                    <button wire:click="confirmAndSave"
                            {{ empty($recommendations) ? 'disabled' : '' }}
                            class="w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition flex items-center justify-center space-x-1.5 disabled:opacity-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span>Konfirmasi & Simpan Jadwal Kegiatan</span>
                    </button>

                    @if ($hasRunSimulation && count($recommendations) > 0)
                        <button type="button" wire:click="$toggle('showOverridePanel')"
                                class="w-full py-1 text-slate-500 hover:text-slate-800 text-[11px] font-medium transition text-center block">
                            {{ $showOverridePanel ? '▲ Tutup Menu Override' : '▼ Perlu tukar petugas? Buka Menu Override Manual' }}
                        </button>

                        <!-- Panel Override Manual -->
                        @if ($showOverridePanel)
                            <div class="p-3 bg-white rounded-lg border border-amber-200 text-xs space-y-2 mt-2">
                                <span class="font-bold text-amber-800 text-[11px] block">Tukar Personil Petugas (Override Manual)</span>
                                <div>
                                    <label class="text-[10px] text-slate-500 block mb-0.5">Petugas yang diganti:</label>
                                    <select wire:model="overrideOldProtoId" class="w-full p-1.5 text-xs border border-slate-200 rounded bg-white">
                                        @foreach ($recommendations as $rec)
                                            <option value="{{ $rec['protokol']['id'] }}">{{ $rec['protokol']['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="text-[10px] text-slate-500 block mb-0.5">Diganti dengan protokol:</label>
                                    <select wire:model="overrideNewProtoId" class="w-full p-1.5 text-xs border border-slate-200 rounded bg-white">
                                        <option value="">-- Pilih Protokol Pengganti --</option>
                                        @foreach ($activeProtokols as $ap)
                                            <option value="{{ $ap->id }}">{{ $ap->name }} ({{ $ap->phone_wa }})</option>
                                        @endforeach
                                    </select>
                                    @error('overrideNewProtoId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="text-[10px] text-slate-500 block mb-0.5">Alasan Penggantian (Wajib untuk Audit Log):</label>
                                    <input type="text" wire:model="overrideReason" placeholder="Contoh: Petugas berhalangan dinas luar..."
                                           class="w-full p-1.5 text-xs border border-slate-200 rounded">
                                    @error('overrideReason') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <button type="button" wire:click="applyManualOverride"
                                        class="w-full py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded text-[11px] transition">
                                    Terapkan Override & Simpan Catatan Audit
                                </button>
                            </div>
                        @endif
                    @endif
                </div>

            </div>

        </div>
    </div>

</div>
