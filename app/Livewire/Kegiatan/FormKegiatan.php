<?php

namespace App\Livewire\Kegiatan;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Services\Scheduling\GapRuleChecker;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FormKegiatan extends Component
{
    // Mode State
    public bool $isEditing = false;

    public ?int $kegiatanId = null;

    public bool $showDeleteModal = false;

    // Form Input Properties
    public string $title = '';

    public ?int $jenis_kegiatan_id = null;

    public int $jumlah_protokol = 2;

    public string $start_date = '';

    public string $start_time = '08:30';

    public string $end_date = '';

    public string $end_time = '12:00';

    public string $detail = '';

    // Auto-detected Indicators
    public string $detectedHari = 'Hari Kerja';

    public string $detectedFee = 'Dibayar (Hari Kerja)';

    public string $detectedLokasi = 'Dalam Kota';

    public bool $isWeekend = false;

    public bool $isHoliday = false;

    // Simulation & Engine Output
    public bool $hasRunSimulation = false;

    public array $recommendations = [];

    public string $relaxationLevel = 'strict';

    public ?string $relaxationWarning = null;

    public int $totalEligible = 0;

    // Manual Override & Swap State
    public bool $showOverridePanel = false;

    public ?int $overrideOldProtoId = null;

    public ?int $overrideNewProtoId = null;

    public string $overrideReason = '';

    // Modal Ganti Personil (Direct Swap)
    public bool $showSwapModal = false;

    public ?int $swapOldProtoId = null;

    public string $swapOldProtoName = '';

    public ?int $swapNewProtoId = null;

    public string $swapReason = '';

    // Modal Tambah Personil Manual
    public bool $showAddManualModal = false;

    public ?int $manualProtoId = null;

    public string $manualReason = 'Dipilih secara manual oleh Admin';

    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:4|max:255',
            'jenis_kegiatan_id' => 'required|exists:jenis_kegiatan,id',
            'jumlah_protokol' => 'required|integer|min:1|max:10',
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                // BUG-03: Mencegah waktu selesai lebih awal atau sama dengan waktu mulai (durasi minus / nol)
                function ($attribute, $value, $fail) {
                    if ($this->start_date && $this->start_time && $this->end_time) {
                        $start = Carbon::parse("{$this->start_date} {$this->start_time}");
                        $end = Carbon::parse("{$value} {$this->end_time}");
                        if ($end->lte($start)) {
                            $fail('Waktu selesai kegiatan harus lebih akhir dari waktu mulai kegiatan.');
                        }
                    }
                },
            ],
            'end_time' => 'required|date_format:H:i',
            'detail' => 'nullable|string|max:1000',
        ];
    }

    public function mount(?string $date = null, ?Kegiatan $kegiatan = null): void
    {
        if ($kegiatan && $kegiatan->exists) {
            $this->isEditing = true;
            $this->kegiatanId = $kegiatan->id;
            $this->title = $kegiatan->title;
            $this->jenis_kegiatan_id = $kegiatan->jenis_kegiatan_id;
            $this->jumlah_protokol = $kegiatan->jumlah_protokol;
            $this->start_date = $kegiatan->start_datetime->toDateString();
            $this->start_time = $kegiatan->start_datetime->format('H:i');
            $this->end_date = $kegiatan->end_datetime->toDateString();
            $this->end_time = $kegiatan->end_datetime->format('H:i');
            $this->detail = $kegiatan->detail ?? '';

            // Load riwayat personil yang saat ini ditugaskan
            $kegiatan->load(['penugasan.protokol']);
            $existingRecs = [];
            foreach ($kegiatan->penugasan as $pen) {
                if ($pen->protokol) {
                    $existingRecs[] = [
                        'protokol' => $pen->protokol->toArray(),
                        'score' => (float) ($pen->score_snapshot ?? 80.00),
                        'reason' => $pen->selection_reason ?? ($pen->is_override ? "Override Manual: {$pen->override_reason}" : 'Petugas Ditugaskan'),
                    ];
                }
            }

            $this->recommendations = $existingRecs;
            $this->hasRunSimulation = true;
            $this->totalEligible = count($existingRecs);

            if (! empty($existingRecs)) {
                $this->overrideOldProtoId = $existingRecs[0]['protokol']['id'];
            }

            $this->updateAutoDetection(preserveRecs: true);
        } else {
            $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::now()->addDay()->toDateString();
            $this->start_date = $targetDate;
            $this->end_date = $targetDate;

            $firstJenis = JenisKegiatan::first();
            if ($firstJenis) {
                $this->jenis_kegiatan_id = $firstJenis->id;
            }

            $this->updateAutoDetection();
        }
    }

    public function updatedJenisKegiatanId(): void
    {
        $this->updateAutoDetection();
    }

    public function updatedStartDate(): void
    {
        $this->end_date = $this->start_date;
        $this->updateAutoDetection();
    }

    public function updatedEndDate(): void
    {
        $this->updateAutoDetection();
    }

    public function updateAutoDetection(bool $preserveRecs = false): void
    {
        if (empty($this->start_date)) {
            return;
        }

        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date ?? $this->start_date);

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        // BUG-05: Pindai seluruh rentang tanggal untuk mendeteksi weekend / hari libur lintas hari
        $hasWeekend = false;
        $hasHoliday = false;
        $holidayName = null;

        $curr = $start->copy();
        while ($curr->lte($end)) {
            if ($curr->isWeekend()) {
                $hasWeekend = true;
            }
            if (HariLibur::isHoliday($curr)) {
                $hasHoliday = true;
                if (! $holidayName) {
                    $holiday = HariLibur::whereDate('tanggal', $curr->toDateString())->first();
                    $holidayName = $holiday->name ?? 'Hari Libur Nasional';
                }
            }
            $curr->addDay();
        }

        $this->isWeekend = $hasWeekend;
        $this->isHoliday = $hasHoliday;

        // Kategori Hari
        if ($this->isHoliday) {
            $this->detectedHari = 'Libur: ' . ($holidayName ?? 'Hari Libur Nasional');
        } elseif ($this->isWeekend) {
            $this->detectedHari = $start->isSameDay($end) ? $start->dayName . ' (Weekend)' : 'Rentang Akhir Pekan (Weekend)';
        } else {
            $this->detectedHari = $start->isSameDay($end) ? $start->dayName . ' (Hari Kerja)' : 'Hari Kerja Biasa';
        }

        // Tipe Fee & Lokasi berdasarkan Template
        $jenis = JenisKegiatan::find($this->jenis_kegiatan_id);
        if ($jenis) {
            if (! $jenis->isPaid()) {
                $this->detectedFee = 'Tidak Dibayar (Rutin)';
            } elseif ($this->isWeekend || $this->isHoliday) {
                $this->detectedFee = 'Dibayar (Luar Hari Kerja)';
            } else {
                $this->detectedFee = 'Dibayar (Hari Kerja)';
            }

            $this->detectedLokasi = $jenis->isLuarKota() ? 'Luar Kota (H+1 Gap)' : 'Dalam Kota';
        }

        // Reset simulasi jika bukan mode edit atau preserveRecs false
        if (! $preserveRecs && ! $this->isEditing) {
            $this->hasRunSimulation = false;
            $this->recommendations = [];
        }
    }

    /**
     * Helper reusable untuk merakit instance Kegiatan in-memory dari input form saat ini.
     */
    protected function buildTemporaryKegiatan(): Kegiatan
    {
        $jenis = JenisKegiatan::find($this->jenis_kegiatan_id);
        $startDateTime = "{$this->start_date} {$this->start_time}:00";
        $endDateTime = "{$this->end_date} {$this->end_time}:00";

        return new Kegiatan([
            'id' => $this->isEditing ? $this->kegiatanId : null,
            'jenis_kegiatan_id' => $this->jenis_kegiatan_id,
            'title' => $this->title,
            'detail' => $this->detail,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'jumlah_protokol' => $this->jumlah_protokol,
            'is_weekend' => $this->isWeekend,
            'is_holiday' => $this->isHoliday,
            'location_type' => $jenis?->location_type->value ?? LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::DRAFT->value,
        ]);
    }

    public function runAutoSchedulerSimulation(SchedulingEngine $engine): void
    {
        $this->validate();

        $tempKegiatan = $this->buildTemporaryKegiatan();

        $output = $engine->generateRecommendation($tempKegiatan);

        $this->recommendations = $output['recommendations']->toArray();
        $this->relaxationLevel = $output['relaxation_level'];
        $this->relaxationWarning = $output['warning'];
        $this->totalEligible = $output['total_eligible'];
        $this->hasRunSimulation = true;

        if (count($this->recommendations) > 0) {
            $this->overrideOldProtoId = $this->recommendations[0]['protokol']['id'];
        }

        session()->flash('info', 'Simulasi algoritma selesai. Rekomendasi siap ditinjau.');
    }

    public function applyManualOverride(GapRuleChecker $gapChecker): void
    {
        $this->validate([
            'overrideOldProtoId' => 'required|exists:protokols,id',
            'overrideNewProtoId' => 'required|exists:protokols,id|different:overrideOldProtoId',
            'overrideReason' => 'required|string|min:5|max:500',
        ]);

        $newProto = Protokol::find($this->overrideNewProtoId);
        $tempKegiatan = $this->buildTemporaryKegiatan();

        if (! $gapChecker->passesAllHardRules($newProto, $tempKegiatan)) {
            $this->addError('overrideNewProtoId', "Pegawai {$newProto->name} tidak memenuhi Hard Rules (mengalami bentrok jadwal atau jeda istirahat kurang).");

            return;
        }

        // Update list rekomendasi in-memory
        foreach ($this->recommendations as $index => $rec) {
            if ($rec['protokol']['id'] == $this->overrideOldProtoId) {
                $this->recommendations[$index]['protokol'] = $newProto->toArray();
                $this->recommendations[$index]['reason'] = "Override Manual: {$this->overrideReason}";
                $this->recommendations[$index]['score'] = 90.00;
                break;
            }
        }

        $this->showOverridePanel = false;
        $this->overrideReason = '';
        session()->flash('success', "Protokol berhasil ditukar dengan {$newProto->name}. Alasan override dicatat.");
    }

    public function openSwapModal(int $oldId): void
    {
        $this->resetValidation();
        $proto = Protokol::findOrFail($oldId);
        $this->swapOldProtoId = $proto->id;
        $this->swapOldProtoName = $proto->name;
        $this->swapNewProtoId = null;
        $this->swapReason = '';
        $this->showSwapModal = true;
    }

    public function applySwapProtocol(GapRuleChecker $gapChecker): void
    {
        $this->validate([
            'swapOldProtoId' => 'required|exists:protokols,id',
            'swapNewProtoId' => 'required|exists:protokols,id|different:swapOldProtoId',
            'swapReason' => 'required|string|min:4|max:500',
        ]);

        $newProto = Protokol::findOrFail($this->swapNewProtoId);
        $tempKegiatan = $this->buildTemporaryKegiatan();

        // Validasi: hanya cek jika protokol pengganti sudah bertugas di kegiatan ini atau memiliki jadwal bentrok di tanggal & waktu yang sama
        $existingIds = collect($this->recommendations)
            ->pluck('protokol.id')
            ->reject(fn($id) => $id == $this->swapOldProtoId)
            ->toArray();

        if (in_array($this->swapNewProtoId, $existingIds)) {
            $this->addError('swapNewProtoId', "Pegawai {$newProto->name} sudah ada dalam susunan tugas kegiatan ini.");

            return;
        }

        if ($gapChecker->hasDirectOverlap($newProto, $tempKegiatan)) {
            $this->addError('swapNewProtoId', "Pegawai {$newProto->name} sudah memiliki jadwal kegiatan lain di tanggal dan waktu yang sama.");

            return;
        }

        // Update susunan penugasan in-memory
        foreach ($this->recommendations as $index => $rec) {
            if ($rec['protokol']['id'] == $this->swapOldProtoId) {
                $this->recommendations[$index]['protokol'] = $newProto->toArray();
                $this->recommendations[$index]['reason'] = "Penggantian Manual: {$this->swapReason}";
                $this->recommendations[$index]['score'] = 90.00;
                break;
            }
        }

        $this->showSwapModal = false;
        $this->reset(['swapOldProtoId', 'swapOldProtoName', 'swapNewProtoId', 'swapReason']);

        session()->flash('success', "Personil protokol berhasil diganti menjadi {$newProto->name}.");
    }

    public function closeSwapModal(): void
    {
        $this->showSwapModal = false;
        $this->reset(['swapOldProtoId', 'swapOldProtoName', 'swapNewProtoId', 'swapReason']);
    }

    public function removeProtocolFromList(int $protoId): void
    {
        $this->recommendations = collect($this->recommendations)
            ->filter(fn($r) => $r['protokol']['id'] != $protoId)
            ->values()
            ->toArray();

        session()->flash('info', 'Personil berhasil dilepaskan dari susunan penugasan kegiatan.');
    }

    public function openAddManualModal(): void
    {
        $this->resetValidation();
        $this->manualProtoId = null;
        $this->manualReason = 'Dipilih secara manual oleh Admin';
        $this->showAddManualModal = true;
    }

    public function addManualProtocol(GapRuleChecker $gapChecker): void
    {
        $this->validate([
            'manualProtoId' => 'required|exists:protokols,id',
            'manualReason' => 'required|string|min:4|max:500',
        ]);

        $existingIds = collect($this->recommendations)->pluck('protokol.id')->toArray();
        if (in_array($this->manualProtoId, $existingIds)) {
            $this->addError('manualProtoId', 'Personil ini sudah ada dalam susunan tugas kegiatan.');

            return;
        }

        $proto = Protokol::findOrFail($this->manualProtoId);
        $tempKegiatan = $this->buildTemporaryKegiatan();

        // Validasi: hanya cek jika protokol yang ditambahkan memiliki jadwal bentrok di tanggal & waktu yang sama
        if ($gapChecker->hasDirectOverlap($proto, $tempKegiatan)) {
            $this->addError('manualProtoId', "Pegawai {$proto->name} sudah memiliki jadwal kegiatan lain di tanggal dan waktu yang sama.");

            return;
        }

        $this->recommendations[] = [
            'protokol' => $proto->toArray(),
            'score' => 85.00,
            'reason' => "Manual: {$this->manualReason}",
        ];

        $this->hasRunSimulation = true;
        $this->showAddManualModal = false;
        $this->reset(['manualProtoId', 'manualReason']);

        session()->flash('success', "{$proto->name} berhasil ditambahkan ke susunan personil bertugas.");
    }

    public function closeAddManualModal(): void
    {
        $this->showAddManualModal = false;
        $this->reset(['manualProtoId', 'manualReason']);
    }

    public function confirmAndSave(SchedulingEngine $engine)
    {
        $this->validate();

        $startDateTime = "{$this->start_date} {$this->start_time}:00";
        $endDateTime = "{$this->end_date} {$this->end_time}:00";
        $jenis = JenisKegiatan::findOrFail($this->jenis_kegiatan_id);

        if ($this->isEditing && $this->kegiatanId) {
            $kegiatan = Kegiatan::findOrFail($this->kegiatanId);
            $kegiatan->update([
                'jenis_kegiatan_id' => $jenis->id,
                'title' => $this->title,
                'detail' => $this->detail,
                'start_datetime' => $startDateTime,
                'end_datetime' => $endDateTime,
                'jumlah_protokol' => $this->jumlah_protokol,
                'is_weekend' => $this->isWeekend,
                'is_holiday' => $this->isHoliday,
                'location_type' => $jenis->location_type->value,
            ]);

            $chosenIds = null;
            if (! empty($this->recommendations)) {
                $chosenIds = collect($this->recommendations)->pluck('protokol.id')->toArray();
                // Hapus penugasan untuk protokol yang tidak lagi terpilih
                $kegiatan->penugasan()->whereNotIn('protokol_id', $chosenIds)->delete();
            }

            $engine->assignAndCommit($kegiatan, $chosenIds, Auth::id());

            session()->flash('success', "Kegiatan '{$kegiatan->title}' berhasil diperbarui!");
        } else {
            $kegiatan = Kegiatan::create([
                'jenis_kegiatan_id' => $jenis->id,
                'title' => $this->title,
                'detail' => $this->detail,
                'start_datetime' => $startDateTime,
                'end_datetime' => $endDateTime,
                'jumlah_protokol' => $this->jumlah_protokol,
                'is_weekend' => $this->isWeekend,
                'is_holiday' => $this->isHoliday,
                'location_type' => $jenis->location_type->value,
                'status' => KegiatanStatus::DRAFT->value,
                'created_by' => Auth::id(),
            ]);

            // Simpan penugasan ke database
            $chosenIds = null;
            if (! empty($this->recommendations)) {
                $chosenIds = collect($this->recommendations)->pluck('protokol.id')->toArray();
            }

            $engine->assignAndCommit($kegiatan, $chosenIds, Auth::id());

            session()->flash('success', "Kegiatan '{$kegiatan->title}' berhasil dijadwalkan dan diterbitkan!");
        }

        return redirect()->route('dashboard');
    }

    public function confirmDeleteKegiatan(): void
    {
        $this->showDeleteModal = true;
    }

    public function deleteKegiatan()
    {
        if (! $this->kegiatanId) {
            return;
        }

        $kegiatan = Kegiatan::findOrFail($this->kegiatanId);
        $title = $kegiatan->title;
        $kegiatan->delete();

        session()->flash('success', "Kegiatan '{$title}' dan seluruh penugasan terkait berhasil dihapus.");

        return redirect()->route('dashboard');
    }

    public function cancelDeleteKegiatan(): void
    {
        $this->showDeleteModal = false;
    }

    public function render()
    {
        $jenisList = JenisKegiatan::orderBy('name')->get();
        $selectedIds = collect($this->recommendations)->pluck('protokol.id')->toArray();

        $activeProtokols = Protokol::active()->orderBy('name')->get();

        // Personil yang tersedia untuk ditukar (mengecualikan yang sudah terpilih, kecuali yang sedang diganti)
        $excludedForSwap = array_diff($selectedIds, [$this->swapOldProtoId ?? 0]);
        $availableProtokolsForSwap = Protokol::active()
            ->when(! empty($excludedForSwap), fn($q) => $q->whereNotIn('id', $excludedForSwap))
            ->orderBy('name')
            ->get();

        // Personil yang tersedia untuk ditambahkan manual (mengecualikan semua yang sudah ada di list)
        $availableProtokolsForAdd = Protokol::active()
            ->when(! empty($selectedIds), fn($q) => $q->whereNotIn('id', $selectedIds))
            ->orderBy('name')
            ->get();

        return view('livewire.kegiatan.form-kegiatan', [
            'jenisList' => $jenisList,
            'activeProtokols' => $activeProtokols,
            'availableProtokolsForSwap' => $availableProtokolsForSwap,
            'availableProtokolsForAdd' => $availableProtokolsForAdd,
        ])->layout('layouts.app', ['title' => $this->isEditing ? 'Ubah Kegiatan & Penugasan' : 'Buat Kegiatan & Auto-Scheduling']);
    }
}
