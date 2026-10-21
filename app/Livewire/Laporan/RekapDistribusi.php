<?php

namespace App\Livewire\Laporan;

use App\Models\Protokol;
use Carbon\Carbon;
use Livewire\Component;

class RekapDistribusi extends Component
{
    public int $selectedMonth;

    public int $selectedYear;

    public function boot(): void
    {
        abort_unless(auth()->user()?->can('manage-master'), 403, 'Akses ditolak. Anda tidak memiliki izin untuk melihat laporan distribusi.');
    }

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->month;
        $this->selectedYear = Carbon::now()->year;
    }

    public function render()
    {
        $protokols = Protokol::active()
            ->withMonthlyMetrics($this->selectedMonth, $this->selectedYear)
            ->orderBy('name')
            ->get();

        $monthName = Carbon::createFromDate($this->selectedYear, $this->selectedMonth, 1)->translatedFormat('F');

        return view('livewire.laporan.rekap-distribusi', [
            'protokols' => $protokols,
            'monthName' => $monthName,
        ])->layout('layouts.app', ['title' => 'Rekapitulasi Keseimbangan Tugas']);
    }
}
