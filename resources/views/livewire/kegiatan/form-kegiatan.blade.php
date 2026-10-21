<div class="space-y-6">

    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    {{ $isEditing ? 'Form Ubah Kegiatan & Penugasan Protokol' : 'Form Buat Kegiatan & Engine Penjadwalan Otomatis' }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $isEditing ? 'Perbarui rincian kegiatan atau sesuaikan petugas protokol yang ditugaskan.' : 'Sistem secara instan menyeleksi kandidat protokol teradil berdasarkan 4 Hard Rules & 4 Soft Rules.' }}
                </p>
            </div>
            <div class="flex items-center space-x-2">
                @if ($isEditing)
                    <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2.5 py-1 rounded-full shrink-0">
                        Mode Edit #{{ $kegiatanId }}
                    </span>
                    <a href="{{ route('dashboard') }}" class="text-xs text-slate-500 hover:text-slate-800 font-medium">
                        Kembali
                    </a>
                @else
                    <span class="bg-blue-100 text-[#1A5276] text-xs font-semibold px-2.5 py-1 rounded-full shrink-0">
                        Livewire Reactive Engine
                    </span>
                @endif
            </div>
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
                    <span>{{ $isEditing ? 'Jalankan Ulang Algoritma Penjadwalan' : 'Jalankan Algoritma Penjadwalan Otomatis' }}</span>
                </button>
            </div>

            <!-- RIGHT 5 COLS: HASIL REKOMENDASI ENGINE -->
            <div class="lg:col-span-5 bg-slate-50 p-5 rounded-xl border border-slate-200 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <span class="text-xs font-bold text-slate-900 flex items-center space-x-1.5">
                            <span class="w-2 h-2 rounded-full {{ $hasRunSimulation ? 'bg-emerald-500 animate-ping' : 'bg-slate-300' }}"></span>
                            <span>{{ $isEditing ? 'Susunan Petugas Protokol' : 'Hasil Rekomendasi Algoritma' }}</span>
                        </span>
                        <span class="text-[11px] font-semibold {{ $hasRunSimulation ? 'text-emerald-700 bg-emerald-100' : 'text-slate-500 bg-slate-200' }} px-2 py-0.5 rounded">
                            {{ $hasRunSimulation ? 'Petugas Terjadwal' : 'Menunggu Simulasi' }}
                        </span>
                    </div>

                    @if ($relaxationWarning)
                        <div class="mt-3 p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800">
                            {{ $relaxationWarning }}
                        </div>
                    @endif

                    <!-- Candidates List -->
                    <div class="space-y-3 mt-4">
                        @if (count($recommendations) > 0)
                            @foreach ($recommendations as $idx => $rec)
                                <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-2xs space-y-2 transition hover:shadow-xs">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="w-5 h-5 rounded-full bg-[#1A5276] text-white flex items-center justify-center font-bold text-[10px] shrink-0">
                                                #{{ $idx + 1 }}
                                            </span>
                                            <div>
                                                <h4 class="font-bold text-slate-900 text-xs">{{ $rec['protokol']['name'] }}</h4>
                                                <span class="text-[10px] text-blue-700 font-medium">{{ $rec['protokol']['phone_wa'] }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1 shrink-0">
                                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                Skor: {{ number_format($rec['score'], 1) }}
                                            </span>
                                            <!-- TOMBOL GANTI PROTOKOL LANGSUNG DARI KARTU -->
                                            <button type="button" wire:click="openSwapModal({{ $rec['protokol']['id'] }})"
                                                    class="px-2 py-0.5 text-[11px] font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 rounded shadow-2xs transition flex items-center space-x-1"
                                                    title="Ganti personil protokol ini">
                                                <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                                <span>Ganti</span>
                                            </button>
                                            <button type="button" wire:click="removeProtocolFromList({{ $rec['protokol']['id'] }})"
                                                    class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition"
                                                    title="Lepaskan personil ini dari tugas">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <p class="text-[11px] text-slate-600 leading-relaxed bg-slate-50 p-2 rounded-lg border border-slate-100">
                                        {{ $rec['reason'] }}
                                    </p>

                                    <div class="flex items-center justify-between text-[10px] text-slate-400 pt-0.5">
                                        <span>Hard Rules: ✅ Lolos</span>
                                        <span class="text-emerald-600 font-semibold">Tersedia & Siap Dinas</span>
                                    </div>
                                </div>
                            @endforeach

                            <!-- Tombol Tambah Personil Manual Tambahan -->
                            <div class="pt-1">
                                <button type="button" wire:click="openAddManualModal"
                                        class="w-full py-2 px-3 border border-dashed border-slate-300 hover:border-[#1A5276] hover:bg-white text-slate-600 hover:text-[#1A5276] rounded-xl text-xs font-semibold flex items-center justify-center space-x-1.5 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    <span>+ Tambah / Pilih Personil Secara Manual</span>
                                </button>
                            </div>
                        @else
                            <div class="py-10 text-center text-slate-400 text-xs space-y-3">
                                <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <p>Belum ada personil protokol yang terpilih.</p>
                                <div class="flex items-center justify-center space-x-2 pt-1">
                                    <button type="button" wire:click="openAddManualModal" class="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold rounded-lg text-xs shadow-2xs transition">
                                        + Pilih Personil Manual
                                    </button>
                                </div>
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
                        <span>{{ $isEditing ? 'Simpan Perubahan Jadwal Kegiatan' : 'Konfirmasi & Simpan Jadwal Kegiatan' }}</span>
                    </button>

                    @if ($isEditing)
                        <button type="button" wire:click="confirmDeleteKegiatan"
                                class="w-full py-2 px-4 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition flex items-center justify-center space-x-1.5">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            <span>Hapus Kegiatan Ini</span>
                        </button>
                    @endif

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
                        <span class="font-bold text-slate-900 text-right">{{ $title }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Waktu Mulai:</span>
                        <span class="font-semibold text-slate-900">{{ $start_date }} {{ $start_time }} WIB</span>
                    </div>
                </div>

                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] mb-4 flex items-start space-x-2">
                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>Penghapusan ini akan membatalkan agenda acara dan membebaskan kembali jadwal protokol terkait.</span>
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

    <!-- MODAL GANTI PERSONIL (DIRECT SWAP) -->
    @if ($showSwapModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2 text-amber-700">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        <h3 class="text-base font-bold text-slate-900">Ganti Personil Protokol</h3>
                    </div>
                    <button wire:click="closeSwapModal" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold">&times;</button>
                </div>

                <div class="space-y-4 mt-4 text-xs">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-slate-500 block text-[11px] mb-0.5">Personil yang akan diganti:</span>
                        <span class="font-bold text-slate-900 text-sm">{{ $swapOldProtoName }}</span>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Pilih Personil Pengganti <span class="text-rose-500">*</span></label>
                        <select wire:model="swapNewProtoId" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                            <option value="">-- Pilih dari Personil Aktif Tersedia --</option>
                            @foreach ($availableProtokolsForSwap as $ap)
                                <option value="{{ $ap->id }}">{{ $ap->name }} ({{ $ap->phone_wa }})</option>
                            @endforeach
                        </select>
                        @error('swapNewProtoId') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Alasan Penggantian (Catatan Audit) <span class="text-rose-500">*</span></label>
                        <textarea wire:model="swapReason" rows="2" placeholder="Contoh: Disposisi pimpinan / Petugas izin dinas luar / Penyesuaian tugas mendadak"
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]"></textarea>
                        @error('swapReason') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button wire:click="closeSwapModal" type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                            Batal
                        </button>
                        <button wire:click="applySwapProtocol" type="button" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center space-x-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Terapkan Penggantian</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL TAMBAH PERSONIL MANUAL -->
    @if ($showAddManualModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2 text-[#1A5276]">
                        <svg class="w-5 h-5 text-[#1A5276]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        <h3 class="text-base font-bold text-slate-900">Tambah Personil Protokol</h3>
                    </div>
                    <button wire:click="closeAddManualModal" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold">&times;</button>
                </div>

                <div class="space-y-4 mt-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Pilih Personil Protokol <span class="text-rose-500">*</span></label>
                        <select wire:model="manualProtoId" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                            <option value="">-- Pilih dari Personil Aktif --</option>
                            @foreach ($availableProtokolsForAdd as $ap)
                                <option value="{{ $ap->id }}">{{ $ap->name }} ({{ $ap->phone_wa }})</option>
                            @endforeach
                        </select>
                        @error('manualProtoId') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Keterangan / Alasan Penugasan <span class="text-rose-500">*</span></label>
                        <textarea wire:model="manualReason" rows="2" placeholder="Contoh: Ditugaskan manual sesuai arahan koordinator"
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]"></textarea>
                        @error('manualReason') <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button wire:click="closeAddManualModal" type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                            Batal
                        </button>
                        <button wire:click="addManualProtocol" type="button" class="px-4 py-2 bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center space-x-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            <span>Tambahkan ke Kegiatan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
