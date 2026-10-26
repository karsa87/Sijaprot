<?php

namespace App\Http\Controllers;

use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaporanDistribusiController extends Controller
{
    /**
     * Display workload distribution report for protocols.
     * Protected by manage-master gate.
     */
    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->can('manage-master'), 403, 'Akses ditolak. Anda tidak memiliki izin untuk melihat laporan distribusi.');

        $now = Carbon::now();
        $month = (int) $request->input('month', $now->month);
        $year = (int) $request->input('year', $now->year);

        $protokols = Protokol::active()
            ->withMonthlyMetrics($month, $year)
            ->with(['kegiatan' => function ($kq) use ($month, $year) {
                $kq->whereMonth('start_datetime', $month)
                    ->whereYear('start_datetime', $year)
                    ->where('status', '!=', 'cancelled')
                    ->where('is_kunker', true)
                    ->select(['kegiatan.id', 'kegiatan.title', 'kegiatan.start_datetime'])
                    ->orderBy('kegiatan.start_datetime', 'asc');
            }])
            ->orderBy('name')
            ->get();

        $monthName = Carbon::createFromDate($year, $month, 1)->translatedFormat('F');

        return Inertia::render('Laporan/RekapDistribusi', [
            'protokols' => $protokols,
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'monthName' => $monthName,
        ]);
    }
}
