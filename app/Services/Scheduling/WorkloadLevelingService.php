<?php

namespace App\Services\Scheduling;

use App\Models\Protokol;
use Illuminate\Support\Collection;

class WorkloadLevelingService
{
    public const LEVEL_OVERWORK = 'overwork';

    public const LEVEL_SEIMBANG = 'seimbang';

    public const LEVEL_HARUS_DITUGASKAN = 'harus_ditugaskan';

    public const TEAM_STATUS_SANGAT_SEIMBANG = 'sangat_seimbang';

    public const TEAM_STATUS_CUKUP_SEIMBANG = 'cukup_seimbang';

    public const TEAM_STATUS_PERLU_PEMERATAAN = 'perlu_pemerataan';

    /**
     * Evaluate the entire active protocol team workload fairness and compute
     * team fairness index, team status, and individual protocol workload levels.
     * Aligned with PRD v2.0 Section 7.
     *
     * @param  Collection<int, Protokol>  $protokols
     * @return array{
     *     indeks: int,
     *     status: string,
     *     status_label: string,
     *     badge_color: string,
     *     badge_class: string,
     *     penjelasan: string,
     *     fairness_gap: int,
     *     avg_tasks: float,
     *     counts: array{overwork: int, seimbang: int, harus_ditugaskan: int},
     *     summary_text: string,
     *     diagnosa_text: string,
     *     evaluations: array<int, array>
     * }
     */
    public function evaluateTeam(Collection $protokols): array
    {
        if ($protokols->isEmpty()) {
            return [
                'indeks' => 100,
                'status' => self::TEAM_STATUS_SANGAT_SEIMBANG,
                'status_label' => 'Sangat Seimbang',
                'badge_color' => 'emerald',
                'badge_class' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'penjelasan' => 'Belum ada data protokol untuk dievaluasi.',
                'fairness_gap' => 0,
                'avg_tasks' => 0.0,
                'counts' => ['overwork' => 0, 'seimbang' => 0, 'harus_ditugaskan' => 0],
                'summary_text' => '0 Overwork | 0 Seimbang | 0 Harus Ditugaskan',
                'diagnosa_text' => 'Tidak ada aktivitas pada periode ini.',
                'evaluations' => [],
            ];
        }

        $pCount = $protokols->count();
        $totalTasks = (int) $protokols->sum('total_tugas_bulan_ini');
        $avgTasks = round($totalTasks / max(1, $pCount), 1);

        $maxTasks = (int) $protokols->max('total_tugas_bulan_ini');
        $minTasks = (int) $protokols->min('total_tugas_bulan_ini');
        $fairnessGap = max(0, $maxTasks - $minTasks);

        // Formula Indeks PRD v2.0 Section 7.3:
        // Gap <= 1 => 100%, Gap > 1 => max(60%, 100% - (Gap * 10%))
        $indeks = $fairnessGap <= 1 ? 100 : max(60, 100 - ($fairnessGap * 10));

        $counts = [
            self::LEVEL_OVERWORK => 0,
            self::LEVEL_SEIMBANG => 0,
            self::LEVEL_HARUS_DITUGASKAN => 0,
        ];

        $evaluations = [];
        $overworkNames = [];
        $harusNames = [];

        foreach ($protokols as $protokol) {
            $eval = $this->evaluateProtokol($protokol, $avgTasks, $protokols);
            $evaluations[$protokol->id] = $eval;
            $counts[$eval['level']]++;

            if ($eval['level'] === self::LEVEL_OVERWORK) {
                $overworkNames[] = $protokol->name;
            } elseif ($eval['level'] === self::LEVEL_HARUS_DITUGASKAN) {
                $harusNames[] = $protokol->name;
            }
        }

        // Tentukan status kesehatan keadilan tim
        if ($indeks >= 90) {
            $status = self::TEAM_STATUS_SANGAT_SEIMBANG;
            $statusLabel = 'Sangat Seimbang';
            $badgeColor = 'emerald';
            $badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
            $penjelasan = 'Beban terbagi rata (selisih max-min ≤ 1), tidak ada overwork, distribusi optimal.';
        } elseif ($indeks >= 75) {
            $status = self::TEAM_STATUS_CUKUP_SEIMBANG;
            $statusLabel = 'Cukup Seimbang';
            $badgeColor = 'amber';
            $badgeClass = 'bg-amber-50 text-amber-800 border-amber-200';
            $penjelasan = 'Selisih tugas 2–3 (wajar karena agenda dinas multi-hari), rotasi berjalan normal.';
        } else {
            $status = self::TEAM_STATUS_PERLU_PEMERATAAN;
            $statusLabel = 'Perlu Pemerataan (Tidak Seimbang)';
            $badgeColor = 'rose';
            $badgeClass = 'bg-rose-50 text-rose-800 border-rose-200';
            $penjelasan = 'Tim timpang! Terdapat personil overwork sementara personil lain masih kekurangan penugasan.';
        }

        $summaryText = "{$counts[self::LEVEL_OVERWORK]} Overwork | {$counts[self::LEVEL_SEIMBANG]} Seimbang | {$counts[self::LEVEL_HARUS_DITUGASKAN]} Harus Ditugaskan";

        // Susun teks diagnosa pimpinan
        if (! empty($overworkNames) && ! empty($harusNames)) {
            $diagnosaText = 'Beban '.implode(', ', array_slice($overworkNames, 0, 2)).' melampaui batas rata-rata, sementara '.implode(', ', array_slice($harusNames, 0, 2)).' membutuhkan prioritas penugasan.';
        } elseif (! empty($overworkNames)) {
            $diagnosaText = 'Beban '.implode(', ', array_slice($overworkNames, 0, 2)).' melampaui rata-rata tim dan membutuhkan rotasi istirahat.';
        } elseif (! empty($harusNames)) {
            $diagnosaText = 'Personil '.implode(', ', array_slice($harusNames, 0, 2)).' belum mencapai target penugasan minimal bulan ini.';
        } else {
            $diagnosaText = 'Distribusi penugasan seluruh protokol aktif berada pada rentang proporsional dan sehat.';
        }

        return [
            'indeks' => $indeks,
            'status' => $status,
            'status_label' => $statusLabel,
            'badge_color' => $badgeColor,
            'badge_class' => $badgeClass,
            'penjelasan' => $penjelasan,
            'fairness_gap' => $fairnessGap,
            'avg_tasks' => $avgTasks,
            'counts' => $counts,
            'summary_text' => $summaryText,
            'diagnosa_text' => $diagnosaText,
            'evaluations' => $evaluations,
        ];
    }

    /**
     * Evaluate single protokol workload level based on individual metrics compared to team.
     * Aligned with PRD v2.0 Section 7.2.
     *
     * @return array{
     *     level: string,
     *     label: string,
     *     badge: string,
     *     class: string,
     *     pdf_badge: string,
     *     dot_color: string,
     *     reasons: array<int, string>,
     *     diagnosa: string
     * }
     */
    public function evaluateProtokol(Protokol $protokol, float $avgTasks, Collection $team): array
    {
        $total = (int) ($protokol->total_tugas_bulan_ini ?? 0);
        $kunker = (int) ($protokol->tugas_kunker ?? 0);
        $wfa = (int) ($protokol->tugas_wfa ?? 0);
        $paidTotal = (int) ($protokol->tugas_dibayar_total ?? 0);
        $weekend = (int) ($protokol->tugas_offday ?? 0);

        $totalKunkerInTeam = (int) $team->sum('tugas_kunker');
        $zeroKunkerCount = (int) $team->where('tugas_kunker', 0)->count();
        $avgPaid = (float) ($team->avg('tugas_dibayar_total') ?? 0);
        $totalWfaInTeam = (int) $team->sum('tugas_wfa');

        // ==========================================
        // 1. EVALUASI LEVEL OVERWORK (PRD 7.2.A)
        // ==========================================
        $overworkReasons = [];

        // Kriteria 1: Volume tugas melampaui batas (>= mu + 2)
        if ($total >= ($avgTasks + 2) && $total > 0) {
            $diff = round($total - $avgTasks);
            $overworkReasons[] = "Beban > Rata-rata (+{$diff})";
        }

        // Kriteria 2: Kelelahan akhir pekan (weekend/offday >= 3)
        if ($weekend >= 3) {
            $overworkReasons[] = "Weekend Tinggi ({$weekend}x)";
        }

        // Kriteria 3: Kelebihan Kunker (kunker >= 3 sementara ada rekan yang masih 0 kunker)
        if ($kunker >= 3 && $zeroKunkerCount > 0) {
            $overworkReasons[] = "Kunker Tinggi ({$kunker}x)";
        }

        // Kriteria 4: WFA-Weekend Spike (bertugas di WFA dan akhir pekan >= 2 tugas)
        if ($wfa >= 1 && $weekend >= 2) {
            $overworkReasons[] = 'Lonjakan WFA-Weekend';
        }

        if (! empty($overworkReasons)) {
            return [
                'level' => self::LEVEL_OVERWORK,
                'label' => 'Overwork',
                'badge' => 'rose',
                'class' => 'bg-rose-100 text-rose-800 border-rose-200',
                'pdf_badge' => 'badge-rose',
                'dot_color' => 'bg-rose-500',
                'reasons' => $overworkReasons,
                'diagnosa' => implode(', ', $overworkReasons),
            ];
        }

        // ==========================================
        // 2. EVALUASI LEVEL HARUS DITUGASKAN (PRD 7.2.B)
        // ==========================================
        $harusReasons = [];

        // Kriteria 1: Nol Tugas (Prioritas Mutlak)
        if ($total === 0) {
            $harusReasons[] = 'Nol Tugas (Prioritas Mutlak)';
        }

        // Kriteria 2: Volume Tugas Rendah (<= mu - 2 dan mu >= 2)
        if ($total > 0 && $avgTasks >= 2 && $total <= ($avgTasks - 2)) {
            $diff = round($avgTasks - $total);
            $harusReasons[] = "Jam Terbang Tertinggal (-{$diff})";
        }

        // Kriteria 3: Belum Pernah Kunker (0x sementara tim sudah ada agenda kunker)
        if ($kunker === 0 && $totalKunkerInTeam > 0) {
            $harusReasons[] = 'Belum Pernah Kunker (0x)';
        }

        // Kriteria 4: Kesenjangan Pembayaran (0 dibayar sementara rata-rata tim >= 1.5)
        if ($paidTotal === 0 && $avgPaid >= 1.5) {
            $harusReasons[] = 'Honorarium Minim (0x)';
        }

        // Kriteria 5: Belum Piket WFA (0 WFA sementara tim ada agenda WFA)
        if ($wfa === 0 && $totalWfaInTeam > 0 && $avgTasks >= 1.0) {
            $harusReasons[] = 'Belum Piket WFA';
        }

        if (! empty($harusReasons)) {
            return [
                'level' => self::LEVEL_HARUS_DITUGASKAN,
                'label' => 'Harus Ditugaskan',
                'badge' => 'amber',
                'class' => 'bg-amber-100 text-amber-800 border-amber-200',
                'pdf_badge' => 'badge-warn',
                'dot_color' => 'bg-amber-500',
                'reasons' => $harusReasons,
                'diagnosa' => implode(', ', $harusReasons),
            ];
        }

        // ==========================================
        // 3. LEVEL SEIMBANG (PRD 7.2.C)
        // ==========================================
        return [
            'level' => self::LEVEL_SEIMBANG,
            'label' => 'Seimbang',
            'badge' => 'emerald',
            'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'pdf_badge' => 'badge-optimal',
            'dot_color' => 'bg-emerald-500',
            'reasons' => ['Beban Ideal Sesuai Rata-rata', 'Target Kedinasan Terpenuhi'],
            'diagnosa' => 'Beban Ideal Sesuai Rata-rata, Target Kunker Terpenuhi',
        ];
    }
}
