<?php

namespace App\Services\Scheduling;

use App\Models\Kegiatan;
use App\Models\Protokol;
use Illuminate\Support\Collection;

class RelaxationPipeline
{
    protected GapRuleChecker $gapChecker;

    public function __construct(GapRuleChecker $gapChecker)
    {
        $this->gapChecker = $gapChecker;
    }

    /**
     * Menerapkan pipeline relaksasi aturan bertahap jika kuota protokol kurang.
     *
     * @return array{kandidat: Collection, level: string, warning: ?string}
     */
    public function resolveCandidates(Collection $activePool, Kegiatan $kegiatan, int $needed): array
    {
        // 1. TAHAP NORMAL: Seluruh Hard Rules dievaluasi secara ketat
        $candidates = $activePool->filter(function (Protokol $p) use ($kegiatan) {
            return $this->gapChecker->passesAllHardRules($p, $kegiatan, relaxLuarKotaGap: false);
        });

        if ($candidates->count() >= $needed) {
            return [
                'kandidat' => $candidates,
                'level' => 'strict',
                'warning' => null,
            ];
        }

        // 2. TAHAP RELAKSASI 1: Longgarkan aturan H1 Luar Kota (dari H+1 hari kalender menjadi 24 jam berbasis waktu)
        $candidatesRelaxed = $activePool->filter(function (Protokol $p) use ($kegiatan) {
            return $this->gapChecker->passesAllHardRules($p, $kegiatan, relaxLuarKotaGap: true);
        });

        if ($candidatesRelaxed->count() >= $needed) {
            return [
                'kandidat' => $candidatesRelaxed,
                'level' => 'relaxed_out_of_town_gap',
                'warning' => 'Relaksasi Otomatis Diterapkan: Jeda istirahat dinas luar kota disesuaikan menjadi 24 jam untuk memenuhi kuota penugasan.',
            ];
        }

        // 3. TAHAP RELAKSASI 2: Jika personil aktif tetap kurang dari kuota
        return [
            'kandidat' => $candidatesRelaxed,
            'level' => 'insufficient_pool',
            'warning' => "Peringatan Sistem: Jumlah personil yang tersedia ({$candidatesRelaxed->count()}) kurang dari kuota yang diminta ({$needed}). Admin dapat menugaskan personil yang ada atau menambah protokol aktif.",
        ];
    }
}
