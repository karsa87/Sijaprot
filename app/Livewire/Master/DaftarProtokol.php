<?php

namespace App\Livewire\Master;

use App\Enums\ProtokolStatus;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class DaftarProtokol extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public int $selectedMonth;
    public int $selectedYear;

    // State Modal Form (Tambah/Edit)
    public bool $showFormModal = false;
    public bool $isEditing = false;
    public ?int $protokolId = null;
    public string $name = '';
    public string $phone_wa = '';
    public string $nip = '';
    public string $status = 'active';
    public string $notes = '';

    // State Modal Statistik Individu
    public bool $showStatsModal = false;
    public ?Protokol $statProtokol = null;
    public array $statBreakdown = [];
    public array $statHistory = [];

    // State Modal Konfirmasi Hapus
    public bool $showDeleteModal = false;
    public ?int $deletingProtokolId = null;
    public string $deletingProtokolName = '';
    public int $deletingProtokolTaskCount = 0;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'phone_wa' => 'required|string|min:8|max:25',
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('protokols', 'nip')->ignore($this->protokolId)],
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->month;
        $this->selectedYear = Carbon::now()->year;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $id): void
    {
        $proto = Protokol::findOrFail($id);
        $newStatus = ($proto->status === ProtokolStatus::ACTIVE) ? ProtokolStatus::INACTIVE : ProtokolStatus::ACTIVE;
        $proto->update(['status' => $newStatus->value]);

        session()->flash('success', "Status {$proto->name} berhasil diubah menjadi {$newStatus->label()}.");
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->reset(['protokolId', 'name', 'phone_wa', 'nip', 'notes']);
        $this->status = 'active';
        $this->isEditing = false;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $proto = Protokol::findOrFail($id);
        $this->protokolId = $proto->id;
        $this->name = $proto->name;
        $this->phone_wa = $proto->phone_wa;
        $this->nip = $proto->nip ?? '';
        $this->status = $proto->status->value;
        $this->notes = $proto->notes ?? '';
        $this->isEditing = true;
        $this->showFormModal = true;
    }

    public function saveProtokol(): void
    {
        $validated = $this->validate();

        if ($this->isEditing && $this->protokolId) {
            $proto = Protokol::findOrFail($this->protokolId);
            $proto->update($validated);
            session()->flash('success', "Data pegawai {$proto->name} berhasil diperbarui.");
        } else {
            Protokol::create($validated);
            session()->flash('success', 'Pegawai protokol baru berhasil ditambahkan.');
        }

        $this->showFormModal = false;
    }

    public function openStatsModal(int $id): void
    {
        $this->statProtokol = Protokol::where('id', $id)
            ->withMonthlyMetrics($this->selectedMonth, $this->selectedYear)
            ->firstOrFail();

        // Riwayat tugas terindeks
        $this->statHistory = Penugasan::where('protokol_id', $id)
            ->with(['kegiatan.jenisKegiatan'])
            ->latest('assigned_at')
            ->take(6)
            ->get()
            ->toArray();

        $this->showStatsModal = true;
    }

    public function closeStatsModal(): void
    {
        $this->showStatsModal = false;
        $this->statProtokol = null;
        $this->statHistory = [];
    }

    public function confirmDelete(int $id): void
    {
        $proto = Protokol::findOrFail($id);
        $this->deletingProtokolId = $proto->id;
        $this->deletingProtokolName = $proto->name;
        $this->deletingProtokolTaskCount = $proto->penugasan()->count();
        $this->showDeleteModal = true;
    }

    public function deleteProtokol(): void
    {
        if (!$this->deletingProtokolId) {
            return;
        }

        $proto = Protokol::findOrFail($this->deletingProtokolId);
        $name = $proto->name;

        $proto->delete();

        $this->showDeleteModal = false;
        $this->reset(['deletingProtokolId', 'deletingProtokolName', 'deletingProtokolTaskCount']);

        session()->flash('success', "Pegawai protokol '{$name}' berhasil dihapus (diarsipkan dengan aman).");
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingProtokolId', 'deletingProtokolName', 'deletingProtokolTaskCount']);
    }

    public function render()
    {
        $query = Protokol::search($this->search)
            ->withMonthlyMetrics($this->selectedMonth, $this->selectedYear);

        if ($this->statusFilter === 'active') {
            $query->active();
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('status', 'inactive');
        }

        $protokols = $query->orderBy('name')->paginate(10);

        return view('livewire.master.daftar-protokol', [
            'protokols' => $protokols,
        ])->layout('layouts.app', ['title' => 'Master Pegawai Protokol']);
    }
}
