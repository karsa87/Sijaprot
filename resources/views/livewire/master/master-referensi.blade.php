<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT 7 COLS: MASTER JENIS KEGIATAN -->
        <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Master Jenis Kegiatan (Template)</h3>
                    <p class="text-xs text-slate-500">Menentukan pembayaran dan klasifikasi dinas secara otomatis</p>
                </div>
                <button wire:click="openCreateJenisModal"
                        class="px-2.5 py-1 bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold rounded-md shadow-2xs transition">
                    + Tambah Jenis
                </button>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse ($jenisKegiatans as $j)
                    <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-slate-900 block">{{ $j->name }}</span>
                                <code class="text-[10px] bg-slate-100 px-1.5 py-0.5 rounded text-slate-600 font-mono">{{ $j->code }}</code>
                            </div>
                            <span class="text-slate-500 text-[11px] block mt-0.5">{{ $j->description ?? '-' }}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if ($j->isPaid())
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold text-[10px]">
                                    Dibayar ({{ $j->payment_day_type?->label() ?? 'Kerja' }})
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[10px]">
                                    Tidak Dibayar
                                </span>
                            @endif

                            <span class="px-2 py-0.5 rounded font-semibold text-[10px] {{ $j->isLuarKota() ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ $j->location_type->label() }}
                            </span>

                            <button wire:click="openEditJenisModal({{ $j->id }})" class="p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-slate-100" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400">Belum ada template jenis kegiatan.</div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT 5 COLS: MASTER HARI LIBUR NASIONAL -->
        <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Hari Libur Nasional</h3>
                    <p class="text-xs text-slate-500">Diinput manual oleh admin per tahun</p>
                </div>
                <div class="flex items-center space-x-2">
                    <select wire:model.live="selectedYear" class="px-2 py-1 text-xs border border-slate-200 rounded bg-white font-semibold">
                        <option value="2025">2025</option>
                        <option value="2026">2026</option>
                        <option value="2027">2027</option>
                    </select>
                    <button wire:click="openCreateLiburModal" class="px-2.5 py-1 bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold rounded-md shadow-2xs transition">
                        + Tambah
                    </button>
                </div>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse ($hariLiburs as $hl)
                    <div class="p-3 flex items-center justify-between hover:bg-slate-50 transition">
                        <div>
                            <span class="font-semibold text-slate-900 block">{{ \Carbon\Carbon::parse($hl->tanggal)->translatedFormat('d F Y') }}</span>
                            <span class="text-slate-500 text-[11px]">{{ $hl->name }}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $hl->type->value === 'nasional' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $hl->type->label() }}
                            </span>
                            <button wire:click="deleteHariLibur({{ $hl->id }})" wire:confirm="Yakin ingin menghapus hari libur ini?" class="text-rose-500 hover:text-rose-700 p-1" title="Hapus">
                                &times;
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400">Belum ada hari libur untuk tahun {{ $selectedYear }}.</div>
                @endforelse
            </div>
        </div>

    </div>

    <!-- MODAL FORM JENIS KEGIATAN -->
    @if ($showJenisModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">{{ $jenisId ? 'Edit Jenis Kegiatan' : 'Tambah Jenis Kegiatan' }}</h3>
                    <button wire:click="$set('showJenisModal', false)" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="saveJenisKegiatan" class="space-y-3 mt-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Jenis Kegiatan *</label>
                        <input type="text" wire:model="jenisName" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]">
                        @error('jenisName') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kode Unik *</label>
                            <input type="text" wire:model="jenisCode" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] uppercase">
                            @error('jenisCode') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Wilayah Dinas *</label>
                            <select wire:model="jenisLocationType" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                                <option value="dalam_kota">Dalam Kota</option>
                                <option value="luar_kota">Luar Kota (H+1 Gap)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tipe Pembayaran *</label>
                            <select wire:model.live="jenisPaymentType" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                                <option value="paid">Dibayar (Honor)</option>
                                <option value="unpaid">Tidak Dibayar (Rutin)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kategori Hari</label>
                            <select wire:model="jenisPaymentDayType" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white" {{ $jenisPaymentType === 'unpaid' ? 'disabled' : '' }}>
                                <option value="workday">Hari Kerja (Senin–Jumat)</option>
                                <option value="offday">Luar Kerja (Weekend/Libur)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Keterangan / SOP</label>
                        <textarea wire:model="jenisDescription" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button type="button" wire:click="$set('showJenisModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-[#1A5276] hover:bg-blue-800 text-white font-semibold rounded-lg">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL FORM HARI LIBUR -->
    @if ($showLiburModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">Tambah Hari Libur</h3>
                    <button wire:click="$set('showLiburModal', false)" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="saveHariLibur" class="space-y-3 mt-4">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Tanggal Libur *</label>
                        <input type="date" wire:model="liburTanggal" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]">
                        @error('liburTanggal') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Hari Libur / Peringatan *</label>
                        <input type="text" wire:model="liburName" placeholder="Contoh: Hari Kemerdekaan RI" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]">
                        @error('liburName') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kategori Libur *</label>
                        <select wire:model="liburType" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                            <option value="nasional">Libur Nasional</option>
                            <option value="cuti_bersama">Cuti Bersama</option>
                            <option value="hari_besar">Hari Besar / Peringatan</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button type="button" wire:click="$set('showLiburModal', false)" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-[#1A5276] hover:bg-blue-800 text-white font-semibold rounded-lg">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
