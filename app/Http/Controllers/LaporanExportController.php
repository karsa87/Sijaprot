<?php

namespace App\Http\Controllers;

use App\Exports\RekapDistribusiExport;
use App\Models\Protokol;
use App\Services\Scheduling\WorkloadLevelingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanExportController extends Controller
{
    public function exportExcel(Request $request)
    {
        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $fileName = "Rekap_Distribusi_Protokol_{$month}_{$year}.xlsx";

        return Excel::download(new RekapDistribusiExport($month, $year), $fileName);
    }

    public function exportPdf(Request $request)
    {
        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $monthName = Carbon::createFromDate($year, $month, 1)->translatedFormat('F');

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

        $levelingService = app(WorkloadLevelingService::class);
        $teamFairness = $levelingService->evaluateTeam($protokols);

        foreach ($protokols as $p) {
            $p->level_beban = $teamFairness['evaluations'][$p->id] ?? null;
        }

        $pdf = Pdf::loadView('exports.rekap-distribusi-pdf', [
            'protokols' => $protokols,
            'teamFairness' => $teamFairness,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $fileName = "Rekap_Distribusi_Protokol_{$month}_{$year}.pdf";

        return $pdf->download($fileName);
    }
}
