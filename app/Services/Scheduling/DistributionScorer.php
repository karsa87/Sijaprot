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
     * Mengukur 5 parameter agregasi tim:
     * - avg_paid (tugas dibayar pasti)
     * - avg_conditional (peluang honorarium tentatif)
     * - avg_kunker (kunjungan kerja)
     * - avg_wfa (piket hari Jum'at WFA)
     * - avg_weekend (tugas akhir pekan / libur)
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
        $totalConditional = 0;
        $totalKunker = 0;
        $totalWfa = 0;
        $totalWeekend = 0;

        foreach ($metrics as $penugasan) {
            $k = $penugasan->kegiatan;
            if ($k) {
                if ($k->jenisKegiatan?->isPaid()) {
                    $totalPaid++;
                }
                if ($k->jenisKegiatan?->isConditional()) {
                    $totalConditional++;
                }
                if ($k->isKunker()) {
                    $totalKunker++;
                }
                if ($k->isWfa()) {
                    $totalWfa++;
                }
                if ($k->is_weekend || $k->is_holiday) {
                    $totalWeekend++;
                }
            }
        }

        $this->teamAverages = [
            'avg_paid' => $totalPaid / $totalMembers,
            'avg_conditional' => $totalConditional / $totalMembers,
            'avg_kunker' => $totalKunker / $totalMembers,
            'avg_wfa' => $totalWfa / $totalMembers,
            'avg_weekend' => $totalWeekend / $totalMembers,
        ];
    }

    /**
     * Hitung skor kelayakan multidimensi individu protokol beserta alasan transparan.
     * Skor lebih tinggi = Lebih diprioritaskan.
     *
     * Formula PRD v2.0:
     * FinalScore = Wmin*Smin + Wkunker*Skunker + Wpay*Spay + Wcond*Scond + Wwfa*Swfa + Woff*Soff + Widle*Sidle (+ SkunkerBoost)
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
        $condCount = $protoData->tugas_conditional ?? 0;
        $kunkerCount = $protoData->tugas_kunker ?? 0;
        $wfaCount = $protoData->tugas_wfa ?? 0;
        $weekendCount = $protoData->tugas_offday ?? 0;

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
        $avgCond = $this->teamAverages['avg_conditional'] ?? 0.5;
        $avgKunker = $this->teamAverages['avg_kunker'] ?? 0.5;
        $avgWfa = $this->teamAverages['avg_wfa'] ?? 0.5;
        $avgWeekend = $this->teamAverages['avg_weekend'] ?? 0.5;

        // ==========================================
        // PERHITUNGAN BOBOT MULTIDIMENSI (PRD v2.0)
        // ==========================================
        // 1. Smin (Bobot 50.0): Wajib minimal 1x tugas per bulan (Aturan H2)
        $sMinMonth = ($totalMonth == 0) ? 50.0 : 0.0;

        // 2. Skunker (Bobot 35.0): Keseimbangan dinas Kunker
        $sBalanceKunker = max(0.0, ($avgKunker - $kunkerCount) * 35.0);

        // 3. Spay (Bobot 15.0): Keseimbangan perolehan honorarium pasti
        $sBalancePay = max(0.0, ($avgPaid - $paidCount) * 15.0);

        // 4. Scond (Bobot 10.0): Keseimbangan perolehan peluang honorarium (tentatif)
        $sBalanceCond = max(0.0, ($avgCond - $condCount) * 10.0);

        // 5. Swfa (Bobot 15.0): Keseimbangan piket hari WFA
        $sBalanceWfa = max(0.0, ($avgWfa - $wfaCount) * 15.0);

        // 6. Soff (Bobot 15.0): Keseimbangan tugas akhir pekan/hari libur
        $sBalanceWeekend = max(0.0, ($avgWeekend - $weekendCount) * 15.0);

        // 7. Sidle (Bobot 1.0): Rotasi personil dengan jeda istirahat terlama
        $sIdleTime = (float) $daysSinceLastTask * 1.0;

        // Target Min 1x Kunker per Bulan (PRD 5.3):
        // Jika kegiatan bertipe Kunker dan protokol belum pernah kunker bulan ini, beri boost prioritas +40.0 poin
        $sKunkerBoost = 0.0;
        if ($kegiatan->isKunker() && $kunkerCount == 0) {
            $sKunkerBoost = 40.0;
        }

        $finalScore = round(
            $sMinMonth +
            $sBalanceKunker +
            $sBalancePay +
            $sBalanceCond +
            $sBalanceWfa +
            $sBalanceWeekend +
            $sIdleTime +
            $sKunkerBoost,
            2
        );

        // ==========================================
        // PENYUSUNAN REASONING TEKS TRANSPARAN
        // ==========================================
        $reasons = [];
        if ($totalMonth == 0) {
            $reasons[] = 'Belum pernah bertugas bulan ini (Prioritas Wajib)';
        } else {
            $reasons[] = "Total tugas bulan ini {$totalMonth}x";
        }

        if ($kegiatan->isKunker() && $kunkerCount == 0) {
            $reasons[] = 'Target Kunker: Belum pernah Kunker bulan ini (+40 poin)';
        } elseif ($kunkerCount <= $avgKunker) {
            $reasons[] = "Tugas Kunker ({$kunkerCount}x)";
        }

        if ($paidCount <= $avgPaid) {
            $reasons[] = "Honor pasti di bawah/sesuai rata-rata tim ({$paidCount}x)";
        }

        if ($kegiatan->isWfa() && $wfaCount == 0) {
            $reasons[] = 'Belum pernah piket hari WFA bulan ini';
        }

        if ($weekendCount == 0 && ($kegiatan->is_weekend || $kegiatan->is_holiday)) {
            $reasons[] = 'Belum pernah tugas weekend bulan ini';
        }

        if ($daysSinceLastTask > 0) {
            $reasons[] = "Jeda istirahat {$daysSinceLastTask} hari";
        }

        $reasonText = implode('. ', $reasons).'.';

        return [
            'score' => $finalScore,
            'reason' => $reasonText,
            'breakdown' => [
                'min_month' => $sMinMonth,
                'balance_kunker' => $sBalanceKunker,
                'balance_pay' => $sBalancePay,
                'balance_cond' => $sBalanceCond,
                'balance_wfa' => $sBalanceWfa,
                'balance_weekend' => $sBalanceWeekend,
                'idle_time' => $sIdleTime,
                'kunker_boost' => $sKunkerBoost,
                'total_tasks_this_month' => $totalMonth,
            ],
        ];
    }
}
