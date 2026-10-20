<?php

namespace App\Services\Scheduling;

use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DistributionScorer
{
    /**
     * Cache metrics tim untuk bulan berjalan agar query agregasi
     * hanya dieksekusi 1 kali saat scoring batch kandidat.
     */
    protected array $teamAverages = [];

    /**
     * Hitung metrik rata-rata tim untuk bulan kegiatan.
     */
    public function computeTeamAverages(Collection $activeProtokols, int $month, int $year): void
    {
        $protoIds = $activeProtokols->pluck('id');
        $totalMembers = max(1, $protoIds->count());

        // Ambil data agregat tim dalam 1 query terindeks
        $metrics = Penugasan::whereIn('protokol_id', $protoIds)
            ->whereHas('kegiatan', function ($q) use ($month, $year) {
                $q->whereMonth('start_datetime', $month)
                  ->whereYear('start_datetime', $year)
                  ->where('status', '!=', 'cancelled');
            })
            ->with(['kegiatan.jenisKegiatan'])
            ->get();

        $totalPaid = 0;
        $totalWeekend = 0;
        $totalTrip = 0;

        foreach ($metrics as $penugasan) {
            $k = $penugasan->kegiatan;
            if ($k) {
                if ($k->jenisKegiatan?->isPaid()) {
                    $totalPaid++;
                }
                if ($k->is_weekend || $k->is_holiday) {
                    $totalWeekend++;
                }
                if ($k->isLuarKota()) {
                    $totalTrip++;
                }
            }
        }

        $this->teamAverages = [
            'avg_paid' => $totalPaid / $totalMembers,
            'avg_weekend' => $totalWeekend / $totalMembers,
            'avg_trip' => $totalTrip / $totalMembers,
        ];
    }

    /**
     * Hitung skor kelayakan individu protokol beserta alasan transparan.
     * Skor lebih tinggi = Lebih diprioritaskan.
     *
     * @return array{score: float, reason: string, breakdown: array}
     */
    public function calculateScore(Protokol $protokol, Kegiatan $kegiatan): array
    {
        $targetDate = Carbon::parse($kegiatan->start_datetime);
        $month = $targetDate->month;
        $year = $targetDate->year;

        // Ambil metrik protokol bulan berjalan menggunakan subqueries terindeks
        $protoData = Protokol::where('id', $protokol->id)
            ->withMonthlyMetrics($month, $year)
            ->first();

        $totalMonth = $protoData->total_tugas_bulan_ini ?? 0;
        $paidCount = ($protoData->tugas_dibayar_workday ?? 0) + ($protoData->tugas_dibayar_offday ?? 0);
        $weekendCount = $protoData->tugas_dibayar_offday ?? 0;
        $tripCount = $protoData->trip_luar_kota ?? 0;

        // Cari tugas terakhir protokol untuk menghitung idle time
        $lastAssignment = Penugasan::where('protokol_id', $protokol->id)
            ->whereHas('kegiatan', function ($q) use ($targetDate) {
                $q->where('start_datetime', '<', $targetDate)
                  ->where('status', '!=', 'cancelled');
            })
            ->with(['kegiatan:id,start_datetime'])
            ->latest('assigned_at')
            ->first();

        $daysSinceLastTask = 30; // Default jika belum pernah bertugas sama sekali
        if ($lastAssignment && $lastAssignment->kegiatan) {
            $daysSinceLastTask = min(30, max(0, $targetDate->diffInDays(Carbon::parse($lastAssignment->kegiatan->start_datetime))));
        }

        $avgPaid = $this->teamAverages['avg_paid'] ?? 1.0;
        $avgWeekend = $this->teamAverages['avg_weekend'] ?? 0.5;
        $avgTrip = $this->teamAverages['avg_trip'] ?? 0.5;

        // ==========================================
        // PERHITUNGAN BOBOT SCORING
        // ==========================================
        // W1 (Bobot 50.0): Prioritas tertinggi untuk yang belum bertugas bulan ini
        $sMinMonth = ($totalMonth == 0) ? 50.0 : 0.0;

        // W2 (Bobot 15.0): Keseimbangan tugas berbayar (yang di bawah rata-rata diprioritaskan)
        $sBalancePay = max(0.0, ($avgPaid - $paidCount) * 15.0);

        // W3 (Bobot 15.0): Keseimbangan weekend/libur
        $sBalanceWeekend = max(0.0, ($avgWeekend - $weekendCount) * 15.0);

        // W4 (Bobot 10.0): Keseimbangan perjalanan dinas luar kota
        $sBalanceTrip = max(0.0, ($avgTrip - $tripCount) * 10.0);

        // W5 (Bobot 1.0): Rotasi jeda istirahat terlama
        $sIdleTime = (float) $daysSinceLastTask * 1.0;

        $finalScore = round($sMinMonth + $sBalancePay + $sBalanceWeekend + $sBalanceTrip + $sIdleTime, 2);

        // ==========================================
        // PENYUSUNAN REASONING TEKS TRANSPARAN
        // ==========================================
        $reasons = [];
        if ($totalMonth == 0) {
            $reasons[] = 'Belum pernah bertugas bulan ini (Prioritas Wajib H2)';
        } else {
            $reasons[] = "Total tugas bulan ini {$totalMonth}x";
        }

        if ($paidCount <= $avgPaid) {
            $reasons[] = "Tugas dibayar masih di bawah/sesuai rata-rata tim ({$paidCount}x)";
        }

        if ($weekendCount == 0 && ($kegiatan->is_weekend || $kegiatan->is_holiday)) {
            $reasons[] = 'Belum pernah tugas weekend bulan ini';
        }

        if ($daysSinceLastTask > 0) {
            $reasons[] = "Jeda istirahat {$daysSinceLastTask} hari";
        }

        $reasonText = implode('. ', $reasons) . '.';

        return [
            'score' => $finalScore,
            'reason' => $reasonText,
            'breakdown' => [
                'min_month' => $sMinMonth,
                'balance_pay' => $sBalancePay,
                'balance_weekend' => $sBalanceWeekend,
                'balance_trip' => $sBalanceTrip,
                'idle_time' => $sIdleTime,
                'total_tasks_this_month' => $totalMonth,
            ],
        ];
    }
}
