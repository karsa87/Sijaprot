<?php

namespace App\Livewire\Master;

use App\Enums\LiburType;
use App\Enums\LocationType;
use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MasterReferensi extends Component
{
    // Filter Tahun Hari Libur
    public int $selectedYear;

    // State Modal Jenis Kegiatan
    public bool $showJenisModal = false;
    public ?int $jenisId = null;
    public string $jenisName = '';
    public string $jenisCode = '';
    public string $jenisPaymentType = 'paid';
    public ?string $jenisPaymentDayType = 'workday';
    public string $jenisLocationType = 'dalam_kota';
    public float $jenisEstimatedFee = 0.00;
    public string $jenisDescription = '';

    // State Modal Hari Libur
    public bool $showLiburModal = false;
    public ?int $liburId = null;
    public string $liburTanggal = '';
    public string $liburName = '';
    public string $liburType = 'nasional';

    public function mount(): void
    {
        $this->selectedYear = Carbon::now()->year;
        $this->liburTanggal = Carbon::now()->toDateString();
    }

    // ==========================================
    // ACTION JENIS KEGIATAN
    // ==========================================

    public function openCreateJenisModal(): void
    {
        $this->reset(['jenisId', 'jenisName', 'jenisCode', 'jenisDescription']);
        $this->jenisPaymentType = 'paid';
        $this->jenisPaymentDayType = 'workday';
        $this->jenisLocationType = 'dalam_kota';
        $this->jenisEstimatedFee = 0.00;
        $this->showJenisModal = true;
    }

    public function openEditJenisModal(int $id): void
    {
        $j = JenisKegiatan::findOrFail($id);
        $this->jenisId = $j->id;
        $this->jenisName = $j->name;
        $this->jenisCode = $j->code;
        $this->jenisPaymentType = $j->payment_type->value;
        $this->jenisPaymentDayType = $j->payment_day_type?->value;
        $this->jenisLocationType = $j->location_type->value;
        $this->jenisEstimatedFee = (float) $j->estimated_fee;
        $this->jenisDescription = $j->description ?? '';
        $this->showJenisModal = true;
    }

    public function saveJenisKegiatan(): void
    {
        $this->validate([
            'jenisName' => 'required|string|max:255',
            'jenisCode' => ['required', 'string', 'max:50', Rule::unique('jenis_kegiatan', 'code')->ignore($this->jenisId)],
            'jenisPaymentType' => 'required|in:paid,unpaid',
            'jenisLocationType' => 'required|in:dalam_kota,luar_kota',
            'jenisEstimatedFee' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'name' => $this->jenisName,
            'code' => strtoupper($this->jenisCode),
            'payment_type' => $this->jenisPaymentType,
            'payment_day_type' => ($this->jenisPaymentType === 'paid') ? $this->jenisPaymentDayType : null,
            'location_type' => $this->jenisLocationType,
            'estimated_fee' => $this->jenisEstimatedFee,
            'description' => $this->jenisDescription,
        ];

        if ($this->jenisId) {
            JenisKegiatan::findOrFail($this->jenisId)->update($data);
            session()->flash('success', 'Template jenis kegiatan berhasil diperbarui.');
        } else {
            JenisKegiatan::create($data);
            session()->flash('success', 'Jenis kegiatan baru berhasil dibuat.');
        }

        $this->showJenisModal = false;
    }

    // ==========================================
    // ACTION HARI LIBUR
    // ==========================================

    public function openCreateLiburModal(): void
    {
        $this->reset(['liburId', 'liburName']);
        $this->liburTanggal = "{$this->selectedYear}-" . date('m-d');
        $this->liburType = 'nasional';
        $this->showLiburModal = true;
    }

    public function saveHariLibur(): void
    {
        $this->validate([
            'liburTanggal' => ['required', 'date', Rule::unique('hari_libur', 'tanggal')->ignore($this->liburId)],
            'liburName' => 'required|string|max:255',
            'liburType' => 'required|in:nasional,cuti_bersama,hari_besar',
        ]);

        $year = Carbon::parse($this->liburTanggal)->year;

        $payload = [
            'tanggal' => $this->liburTanggal,
            'tahun' => $year,
            'name' => $this->liburName,
            'type' => $this->liburType,
        ];

        if ($this->liburId) {
            HariLibur::findOrFail($this->liburId)->update($payload);
        } else {
            HariLibur::create($payload);
        }

        session()->flash('success', 'Hari libur berhasil disimpan.');
        $this->showLiburModal = false;
    }

    public function deleteHariLibur(int $id): void
    {
        HariLibur::findOrFail($id)->delete();
        session()->flash('success', 'Hari libur berhasil dihapus.');
    }

    public function render()
    {
        $jenisKegiatans = JenisKegiatan::orderBy('name')->get();
        $hariLiburs = HariLibur::forYear($this->selectedYear)->orderBy('tanggal')->get();

        return view('livewire.master.master-referensi', [
            'jenisKegiatans' => $jenisKegiatans,
            'hariLiburs' => $hariLiburs,
        ])->layout('layouts.app', ['title' => 'Master Jenis Kegiatan & Libur Nasional']);
    }
}
