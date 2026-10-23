<div class="space-y-6">
    <!-- Header & Action Card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Master Pegawai Protokol</h2>
                <p class="text-xs text-slate-500 mt-0.5">Kelola daftar protokol aktif yang masuk dalam perhitungan
                    algoritma penjadwalan.</p>
            </div>
            <div class="flex items-center space-x-2">
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari nama, NIP, atau WhatsApp..."
                    class="px-3 py-1.5 text-xs border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] w-56">

                <select wire:model.live="statusFilter"
                    class="px-3 py-1.5 text-xs border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                    <option value="all">Semua Status</option>
                    <option value="active">Aktif Saja</option>
                    <option value="inactive">Nonaktif Saja</option>
                </select>

                <button wire:click="openCreateModal"
                    class="px-3.5 py-1.5 bg-[#1A5276] hover:bg-blue-800 text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center space-x-1 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
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
                        <th class="px-4 py-3 text-center">Dibayar vs Tidak Dibayar</th>
                        <th class="px-4 py-3 text-center">Weekend</th>
                        <th class="px-4 py-3 text-center">Luar Kota</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($protokols as $p)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs">
                                        {{ substr($p->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-slate-900 block">{{ $p->name }}</span>
                                        @if ($p->nip)
                                            <span class="text-[10px] text-slate-400">NIP. {{ $p->nip }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $p->phone_wa }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleStatus({{ $p->id }})"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold border transition {{ $p->status->badgeClass() }}"
                                    title="Klik untuk ubah status">
                                    {{ $p->status->label() }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-slate-900">
                                {{ $p->total_tugas_bulan_ini }}x
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="text-emerald-700 font-semibold">{{ $p->tugas_dibayar_workday + $p->tugas_dibayar_offday }}x</span>
                                /
                                <span class="text-slate-500">{{ $p->tugas_unpaid }}x</span>
                            </td>
                            <td
                                class="px-4 py-3 text-center font-medium {{ $p->tugas_dibayar_offday > 1 ? 'text-amber-700' : 'text-slate-600' }}">
                                {{ $p->tugas_dibayar_offday }}x
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-slate-600">
                                {{ $p->trip_luar_kota }}x
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end space-x-1.5">
                                    <button wire:click="openStatsModal({{ $p->id }})"
                                        class="p-1 rounded text-blue-700 hover:bg-blue-50 transition"
                                        title="Lihat Statistik">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                            </path>
                                        </svg>
                                    </button>
                                    <button wire:click="openEditModal({{ $p->id }})"
                                        class="p-1 rounded text-slate-500 hover:bg-slate-100 transition"
                                        title="Edit Data">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </button>
                                    <button wire:click="confirmDelete({{ $p->id }})"
                                        class="p-1 rounded text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition"
                                        title="Hapus Pegawai">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-400">
                                Tidak ada data pegawai protokol yang sesuai dengan kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <div>{{ $protokols->links() }}</div>
        </div>
    </div>

    <!-- MODAL FORM CREATE / EDIT -->
    @if ($showFormModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ $isEditing ? 'Ubah Data Protokol' : 'Tambah Protokol Baru' }}</h3>
                    <button wire:click="$set('showFormModal', false)"
                        class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold">&times;</button>
                </div>

                <form wire:submit.prevent="saveProtokol" class="space-y-4 mt-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar <span
                                class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]">
                        @error('name')
                            <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">No. WhatsApp <span
                                    class="text-rose-500">*</span></label>
                            <input type="text" wire:model="phone_wa"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]">
                            @error('phone_wa')
                                <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">NIP (Opsional)</label>
                            <input type="text" wire:model="nip"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]">
                            @error('nip')
                                <span class="text-rose-500 text-[11px] block mt-0.5">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Status Ketersediaan</label>
                        <select wire:model="status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276] bg-white">
                            <option value="active">Aktif (Masuk Pool Jadwal)</option>
                            <option value="inactive">Nonaktif (Cuti / Tugas Lain)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Catatan Tambahan</label>
                        <textarea wire:model="notes" rows="2"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-[#1A5276]"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2 border-t border-slate-100">
                        <button type="button" wire:click="$set('showFormModal', false)"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg">Batal</button>
                        <button type="submit"
                            class="px-4 py-2 bg-[#1A5276] hover:bg-blue-800 text-white font-semibold rounded-lg shadow-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL STATISTIK INDIVIDU -->
    @if ($showStatsModal && $statProtokol)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Statistik Penugasan Pegawai</span>
                        <h3 class="text-base font-bold text-slate-900 mt-0.5">{{ $statProtokol->name }}</h3>
                        <p class="text-xs text-slate-500">{{ $statProtokol->phone_wa }} • Status
                            {{ $statProtokol->status->label() }}</p>
                    </div>
                    <button wire:click="closeStatsModal"
                        class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 font-bold">&times;</button>
                </div>

                <!-- Metrics Grid -->
                <div class="grid grid-cols-4 gap-2 my-4 text-center">
                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[10px] text-slate-400 block font-medium">Total</span>
                        <span
                            class="text-lg font-bold text-slate-900">{{ $statProtokol->total_tugas_bulan_ini }}x</span>
                    </div>
                    <div class="p-2.5 bg-emerald-50 rounded-xl border border-emerald-100">
                        <span class="text-[10px] text-emerald-700 block font-medium">Dibayar</span>
                        <span
                            class="text-lg font-bold text-emerald-700">{{ $statProtokol->tugas_dibayar_workday + $statProtokol->tugas_dibayar_offday }}x</span>
                    </div>
                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[10px] text-slate-500 block font-medium">Tidak Dibayar</span>
                        <span class="text-lg font-bold text-slate-700">{{ $statProtokol->tugas_unpaid }}x</span>
                    </div>
                    <div class="p-2.5 bg-amber-50 rounded-xl border border-amber-100">
                        <span class="text-[10px] text-amber-700 block font-medium">Weekend</span>
                        <span
                            class="text-lg font-bold text-amber-700">{{ $statProtokol->tugas_dibayar_offday }}x</span>
                    </div>
                </div>

                <!-- History -->
                <div>
                    <h4 class="text-xs font-bold text-slate-900 mb-2">Riwayat Penugasan Terbaru</h4>
                    <div class="space-y-2 text-xs max-h-56 overflow-y-auto">
                        @forelse ($statHistory as $task)
                            <div
                                class="p-2.5 bg-slate-50 rounded-lg flex items-center justify-between border border-slate-100">
                                <div>
                                    <span
                                        class="font-semibold block text-slate-800">{{ $task['kegiatan']['title'] ?? 'Kegiatan' }}</span>
                                    <span
                                        class="text-[11px] text-slate-500">{{ \Carbon\Carbon::parse($task['assigned_at'])->translatedFormat('d M Y, H:i') }}</span>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold {{ ($task['kegiatan']['location_type'] ?? '') === 'luar_kota' ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ ($task['kegiatan']['location_type'] ?? '') === 'luar_kota' ? 'Luar Kota' : 'Dalam Kota' }}
                                </span>
                            </div>
                        @empty
                            <p class="text-slate-400 text-center py-4">Belum ada riwayat penugasan.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button wire:click="closeStatsModal"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL KONFIRMASI HAPUS PROTOKOL -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div
                class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center space-x-3 text-rose-600 mb-4">
                    <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Pegawai</h3>
                        <p class="text-xs text-slate-500">Tindakan ini akan mengarsipkan data personil.</p>
                    </div>
                </div>

                <div
                    class="bg-slate-50 p-3.5 rounded-xl border border-slate-100 text-xs text-slate-700 space-y-2 mb-4">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nama Personil:</span>
                        <span class="font-bold text-slate-900">{{ $deletingProtokolName }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Total Riwayat Penugasan:</span>
                        <span class="font-semibold text-slate-900">{{ $deletingProtokolTaskCount }} kegiatan</span>
                    </div>
                </div>

                @if ($deletingProtokolTaskCount > 0)
                    <div
                        class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] mb-4 flex items-start space-x-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Personil ini memiliki <strong>{{ $deletingProtokolTaskCount }} riwayat dinas</strong>.
                            Pegawai akan diarsipkan (soft delete) sehingga tidak muncul lagi di pool penjadwalan baru,
                            namun seluruh arsip laporan masa lalu tetap valid.</span>
                    </div>
                @else
                    <p class="text-xs text-slate-600 mb-4">Apakah Anda yakin ingin menghapus data pegawai protokol
                        <strong>{{ $deletingProtokolName }}</strong>?</p>
                @endif

                <div class="flex items-center justify-end space-x-2 pt-2 border-t border-slate-100">
                    <button wire:click="cancelDelete" type="button"
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                        Batal
                    </button>
                    <button wire:click="deleteProtokol" type="button"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-xs transition flex items-center space-x-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                            </path>
                        </svg>
                        <span>Ya, Hapus Personil</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
