<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProtokolStatus;
use App\Models\HariLibur;
use App\Models\Kegiatan;
use App\Models\Protokol;
use Carbon\Carbon;
use Livewire\Component;

class KalenderMonitoring extends Component
{
    public int $month;
    public int $year;
    public int $selectedDay;

    // State Modal Konfirmasi Hapus Kegiatan
    public bool $showDeleteModal = false;
    public ?int $deletingKegiatanId = null;
    public string $deletingKegiatanTitle = '';
    public int $deletingKegiatanProtokolCount = 0;
    public string $deletingKegiatanTime = '';

    public function mount(?int $day = null): void
    {
        $now = Carbon::now();
        $this->month = $now->month;
        $this->year = $now->year;
        $this->selectedDay = $day ?? min($now->day, $now->daysInMonth);
    }

    public function selectDate(int $day): void
    {
        $this->selectedDay = $day;
    }

    public function prevMonth(): void
    {
        $dt = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->month = $dt->month;
        $this->year = $dt->year;
        $this->selectedDay = 1;
    }

    public function nextMonth(): void
    {
        $dt = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->month = $dt->month;
        $this->year = $dt->year;
        $this->selectedDay = 1;
    }

    public function goToCurrentMonth(): void
    {
        $now = Carbon::now();
        $this->month = $now->month;
        $this->year = $now->year;
        $this->selectedDay = min($now->day, $now->daysInMonth);
    }

    public function scheduleOnSelectedDate()
    {
        $d = str_pad($this->selectedDay, 2, '0', STR_PAD_LEFT);
        $m = str_pad($this->month, 2, '0', STR_PAD_LEFT);
        $dateStr = "{$this->year}-{$m}-{$d}";

        return redirect()->route('kegiatan.create', ['date' => $dateStr]);
    }

    public function confirmDeleteKegiatan(int $id): void
    {
        $kegiatan = Kegiatan::with('protokols')->findOrFail($id);
        $this->deletingKegiatanId = $kegiatan->id;
        $this->deletingKegiatanTitle = $kegiatan->title;
        $this->deletingKegiatanProtokolCount = $kegiatan->protokols->count();
        $this->deletingKegiatanTime = $kegiatan->start_datetime->translatedFormat('d M Y, H:i') . ' WIB';
        $this->showDeleteModal = true;
    }

    public function deleteKegiatan(): void
    {
        if (!$this->deletingKegiatanId) {
            return;
        }

        $kegiatan = Kegiatan::findOrFail($this->deletingKegiatanId);
        $title = $kegiatan->title;

        // Cascade delete membersihkan relasi penugasan dan audit log secara otomatis
        $kegiatan->delete();

        $this->showDeleteModal = false;
        $this->reset(['deletingKegiatanId', 'deletingKegiatanTitle', 'deletingKegiatanProtokolCount', 'deletingKegiatanTime']);

        session()->flash('success', "Kegiatan '{$title}' dan seluruh penugasan terkait berhasil dibatalkan & dihapus.");
    }

    public function cancelDeleteKegiatan(): void
    {
        $this->showDeleteModal = false;
        $this->reset(['deletingKegiatanId', 'deletingKegiatanTitle', 'deletingKegiatanProtokolCount', 'deletingKegiatanTime']);
    }

    public function render()
    {
        $startOfMonth = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;

        // Offset 0 = Senin, 6 = Minggu
        $startDayOfWeek = ($startOfMonth->dayOfWeekIso - 1); // 0 (Senin) s.d. 6 (Minggu)

        // 1. Ambil seluruh kegiatan bulan ini beserta relasi (1x query terindeks composite)
        $kegiatans = Kegiatan::whereBetween('start_datetime', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->with(['jenisKegiatan', 'protokols'])
            ->orderBy('start_datetime')
            ->get();

        // Map kegiatan ke tanggal (1-31)
        $eventsByDay = [];
        foreach ($kegiatans as $k) {
            $dayNum = (int) $k->start_datetime->format('j');
            $eventsByDay[$dayNum][] = $k;
        }

        // 2. Ambil hari libur nasional bulan ini
        $holidays = HariLibur::whereYear('tanggal', $this->year)
            ->whereMonth('tanggal', $this->month)
            ->get()
            ->keyBy(function ($item) {
                return (int) Carbon::parse($item->tanggal)->format('j');
            });

        // 3. Data tanggal terpilih (Selected Date)
        $selectedDateObj = Carbon::createFromDate($this->year, $this->month, $this->selectedDay);
        $selectedEvents = $eventsByDay[$this->selectedDay] ?? [];
        $selectedHoliday = $holidays->get($this->selectedDay);
        $isSelectedWeekend = $selectedDateObj->isWeekend();

        // 4. Metrik Dashboard Utama
        $totalKegiatanBulanIni = $kegiatans->count();
        $totalProtokolAktif = Protokol::active()->count();
        $totalProtokol = Protokol::count();

        // Hitung indeks keadilan (Fairness Index)
        $protoMetrics = Protokol::active()->withMonthlyMetrics($this->month, $this->year)->get();
        $maxTasks = $protoMetrics->max('total_tugas_bulan_ini') ?? 0;
        $minTasks = $protoMetrics->min('total_tugas_bulan_ini') ?? 0;
        $fairnessGap = max(0, $maxTasks - $minTasks);
        $indeksKeseimbangan = $fairnessGap <= 1 ? 96 : max(70, 100 - ($fairnessGap * 10));

        return view('livewire.dashboard.kalender-monitoring', [
            'monthName' => $startOfMonth->translatedFormat('F'),
            'daysInMonth' => $daysInMonth,
            'startOffset' => $startDayOfWeek,
            'eventsByDay' => $eventsByDay,
            'holidays' => $holidays,
            'selectedDateObj' => $selectedDateObj,
            'selectedEvents' => $selectedEvents,
            'selectedHoliday' => $selectedHoliday,
            'isSelectedWeekend' => $isSelectedWeekend,
            'totalKegiatanBulanIni' => $totalKegiatanBulanIni,
            'totalProtokolAktif' => $totalProtokolAktif,
            'totalProtokol' => $totalProtokol,
            'indeksKeseimbangan' => $indeksKeseimbangan,
        ])->layout('layouts.app', ['title' => 'Dashboard Monitoring Kalender']);
    }
}
