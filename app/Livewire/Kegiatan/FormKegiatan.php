<?php

namespace App\Livewire\Kegiatan;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FormKegiatan extends Component
{
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

    // Manual Override State
    public bool $showOverridePanel = false;
    public ?int $overrideOldProtoId = null;
    public ?int $overrideNewProtoId = null;
    public string $overrideReason = '';

    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:4|max:255',
            'jenis_kegiatan_id' => 'required|exists:jenis_kegiatan,id',
            'jumlah_protokol' => 'required|integer|min:1|max:10',
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_date' => 'required|date|after_or_equal:start_date',
            'end_time' => 'required|date_format:H:i',
            'detail' => 'nullable|string|max:1000',
        ];
    }

    public function mount(?string $date = null): void
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::now()->addDay()->toDateString();
        $this->start_date = $targetDate;
        $this->end_date = $targetDate;

        $firstJenis = JenisKegiatan::first();
        if ($firstJenis) {
            $this->jenis_kegiatan_id = $firstJenis->id;
        }

        $this->updateAutoDetection();
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

    public function updateAutoDetection(): void
    {
        if (empty($this->start_date)) {
            return;
        }

        $dt = Carbon::parse($this->start_date);
        $this->isWeekend = $dt->isWeekend();
        $this->isHoliday = HariLibur::isHoliday($dt);

        // Kategori Hari
        if ($this->isHoliday) {
            $holiday = HariLibur::whereDate('tanggal', $dt->toDateString())->first();
            $this->detectedHari = 'Libur: ' . ($holiday->name ?? 'Hari Libur Nasional');
        } elseif ($this->isWeekend) {
            $this->detectedHari = $dt->dayName . ' (Weekend)';
        } else {
            $this->detectedHari = $dt->dayName . ' (Hari Kerja)';
        }

        // Tipe Fee & Lokasi berdasarkan Template
        $jenis = JenisKegiatan::find($this->jenis_kegiatan_id);
        if ($jenis) {
            if (!$jenis->isPaid()) {
                $this->detectedFee = 'Tidak Dibayar (Rutin)';
            } elseif ($this->isWeekend || $this->isHoliday) {
                $this->detectedFee = 'Dibayar (Luar Hari Kerja)';
            } else {
                $this->detectedFee = 'Dibayar (Hari Kerja)';
            }

            $this->detectedLokasi = $jenis->isLuarKota() ? 'Luar Kota (H+1 Gap)' : 'Dalam Kota';
        }

        // Reset simulasi jika data tanggal/jenis berubah
        $this->hasRunSimulation = false;
        $this->recommendations = [];
    }

    public function runAutoSchedulerSimulation(SchedulingEngine $engine): void
    {
        $this->validate();

        $startDateTime = "{$this->start_date} {$this->start_time}:00";
        $endDateTime = "{$this->end_date} {$this->end_time}:00";

        // Buat instance transient untuk evaluasi algoritma
        $tempKegiatan = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenis_kegiatan_id,
            'title' => $this->title,
            'detail' => $this->detail,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'jumlah_protokol' => $this->jumlah_protokol,
            'is_weekend' => $this->isWeekend,
            'is_holiday' => $this->isHoliday,
            'location_type' => JenisKegiatan::find($this->jenis_kegiatan_id)?->location_type->value ?? 'dalam_kota',
            'status' => KegiatanStatus::DRAFT->value,
        ]);

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

    public function applyManualOverride(): void
    {
        $this->validate([
            'overrideOldProtoId' => 'required|exists:protokols,id',
            'overrideNewProtoId' => 'required|exists:protokols,id|different:overrideOldProtoId',
            'overrideReason' => 'required|string|min:5|max:500',
        ]);

        $newProto = Protokol::find($this->overrideNewProtoId);

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

    public function confirmAndSave(SchedulingEngine $engine)
    {
        $this->validate();

        $startDateTime = "{$this->start_date} {$this->start_time}:00";
        $endDateTime = "{$this->end_date} {$this->end_time}:00";
        $jenis = JenisKegiatan::findOrFail($this->jenis_kegiatan_id);

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
        if (!empty($this->recommendations)) {
            $chosenIds = collect($this->recommendations)->pluck('protokol.id')->toArray();
        }

        $engine->assignAndCommit($kegiatan, $chosenIds, Auth::id());

        session()->flash('success', "Kegiatan '{$kegiatan->title}' berhasil dijadwalkan dan diterbitkan!");

        return redirect()->route('dashboard');
    }

    public function render()
    {
        $jenisList = JenisKegiatan::orderBy('name')->get();
        $activeProtokols = Protokol::active()->orderBy('name')->get();

        return view('livewire.kegiatan.form-kegiatan', [
            'jenisList' => $jenisList,
            'activeProtokols' => $activeProtokols,
        ])->layout('layouts.app', ['title' => 'Buat Kegiatan & Auto-Scheduling']);
    }
}
