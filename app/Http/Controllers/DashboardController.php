<?php

namespace App\Http\Controllers;

use App\Models\HariLibur;
use App\Models\Kegiatan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display interactive calendar dashboard.
     * Uses bounded monthly query with composite index ['deleted_at', 'start_datetime', 'status'].
     */
    public function index(Request $request): Response
    {
        $now = Carbon::now();
        $month = (int) $request->input('month', $now->month);
        $year = (int) $request->input('year', $now->year);
        $selectedDay = (int) $request->input('day', ($month === $now->month && $year === $now->year) ? min($now->day, $now->daysInMonth) : 1);

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $startDayOfWeek = ($startOfMonth->dayOfWeekIso - 1); // 0 (Senin) s.d. 6 (Minggu)

        // Bounded monthly query with covering composite index
        $kegiatans = Kegiatan::query()
            ->whereBetween('start_datetime', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'cancelled')
            ->with([
                'jenisKegiatan:id,name,code,location_type,payment_type,payment_day_type',
                'protokols:id,name,phone_wa,status',
            ])
            ->orderBy('start_datetime')
            ->get();

        // Bounded holidays query
        $holidays = HariLibur::query()
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->get(['id', 'tanggal', 'name', 'type']);

        // Dashboard Metrics
        $totalKegiatanBulanIni = $kegiatans->count();
        $totalProtokolAktif = Protokol::active()->count();
        $totalProtokol = Protokol::count();

        // Indeks Keadilan (Fairness Index)
        $protoMetrics = Protokol::active()->withMonthlyMetrics($month, $year)->get();
        $maxTasks = $protoMetrics->max('total_tugas_bulan_ini') ?? 0;
        $minTasks = $protoMetrics->min('total_tugas_bulan_ini') ?? 0;
        $fairnessGap = max(0, $maxTasks - $minTasks);
        $indeksKeseimbangan = $fairnessGap <= 1 ? 96 : max(70, 100 - ($fairnessGap * 10));

        return Inertia::render('Dashboard/KalenderMonitoring', [
            'kegiatans' => $kegiatans,
            'holidays' => $holidays,
            'month' => $month,
            'year' => $year,
            'initialDay' => $selectedDay,
            'monthName' => $startOfMonth->translatedFormat('F'),
            'daysInMonth' => $daysInMonth,
            'startOffset' => $startDayOfWeek,
            'stats' => [
                'totalKegiatan' => $totalKegiatanBulanIni,
                'totalProtokolAktif' => $totalProtokolAktif,
                'totalProtokol' => $totalProtokol,
                'indeksKeseimbangan' => $indeksKeseimbangan,
            ],
        ]);
    }

    /**
     * Delete kegiatan from calendar dashboard (Cascade soft delete).
     */
    public function destroyKegiatan(Kegiatan $kegiatan): RedirectResponse
    {
        $title = $kegiatan->title;
        $kegiatan->delete();

        return back()->with('success', "Kegiatan '{$title}' dan seluruh penugasan terkait berhasil dibatalkan & dihapus.");
    }
}
