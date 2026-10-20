<?php

namespace App\Services\Scheduling;

use App\Enums\KegiatanStatus;
use App\Models\AuditOverrideLog;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchedulingEngine
{
    protected GapRuleChecker $gapChecker;
    protected DistributionScorer $scorer;
    protected RelaxationPipeline $pipeline;

    public function __construct(
        GapRuleChecker $gapChecker,
        DistributionScorer $scorer,
        RelaxationPipeline $pipeline
    ) {
        $this->gapChecker = $gapChecker;
        $this->scorer = $scorer;
        $this->pipeline = $pipeline;
    }

    /**
     * Menghasilkan rekomendasi protokol terbaik secara otomatis (Preview / Dry Run)
     * tanpa melakukan penulisan ke database.
     *
     * @return array{
     *   recommendations: Collection,
     *   relaxation_level: string,
     *   warning: ?string,
     *   total_eligible: int,
     *   needed: int
     * }
     */
    public function generateRecommendation(Kegiatan $kegiatan): array
    {
        $needed = $kegiatan->jumlah_protokol;
        $targetDate = Carbon::parse($kegiatan->start_datetime);

        // 1. Ambil pool seluruh protokol yang berstatus aktif
        $activePool = Protokol::active()->get();

        // 2. Evaluasi aturan ketersediaan melalui Relaxation Pipeline
        $resolved = $this->pipeline->resolveCandidates($activePool, $kegiatan, $needed);
        $eligible = $resolved['kandidat'];

        // 3. Hitung rata-rata tim untuk bulan kegiatan berjalan (1x batch calculate)
        $this->scorer->computeTeamAverages($activePool, $targetDate->month, $targetDate->year);

        // 4. Hitung skor kelayakan untuk seluruh kandidat yang lolos
        $scoredCandidates = $eligible->map(function (Protokol $proto) use ($kegiatan) {
            $scoreData = $this->scorer->calculateScore($proto, $kegiatan);

            return [
                'protokol' => $proto,
                'score' => $scoreData['score'],
                'reason' => $scoreData['reason'],
                'breakdown' => $scoreData['breakdown'],
            ];
        });

        // 5. Urutkan berdasarkan skor tertinggi (teradil & terlama belum tugas)
        $ranked = $scoredCandidates->sortByDesc('score')->values();

        // 6. Ambil N personil terbaik sesuai kuota kegiatan
        $topPicks = $ranked->take($needed);

        return [
            'recommendations' => $topPicks,
            'relaxation_level' => $resolved['level'],
            'warning' => $resolved['warning'],
            'total_eligible' => $ranked->count(),
            'needed' => $needed,
        ];
    }

    /**
     * Menugaskan dan menyimpan hasil rekomendasi ke database secara transaksional.
     *
     * @param array<int>|null $customProtocolIds Jika admin ingin menugaskan daftar ID protokol tertentu
     */
    public function assignAndCommit(Kegiatan $kegiatan, ?array $customProtocolIds = null, ?int $assignedByUserId = null): Collection
    {
        return DB::transaction(function () use ($kegiatan, $customProtocolIds, $assignedByUserId) {
            $createdAssignments = collect();

            if ($customProtocolIds && count($customProtocolIds) > 0) {
                // Penugasan dengan ID yang ditentukan admin
                foreach ($customProtocolIds as $protoId) {
                    $proto = Protokol::find($protoId);
                    if ($proto) {
                        $penugasan = Penugasan::updateOrCreate(
                            [
                                'kegiatan_id' => $kegiatan->id,
                                'protokol_id' => $proto->id,
                            ],
                            [
                                'assigned_at' => now(),
                                'assigned_by' => $assignedByUserId,
                                'is_system_assigned' => false,
                                'selection_reason' => 'Dipilih secara manual oleh Admin.',
                            ]
                        );
                        $createdAssignments->push($penugasan);
                    }
                }
            } else {
                // Penugasan otomatis oleh engine
                $result = $this->generateRecommendation($kegiatan);
                foreach ($result['recommendations'] as $rec) {
                    $proto = $rec['protokol'];
                    $penugasan = Penugasan::updateOrCreate(
                        [
                            'kegiatan_id' => $kegiatan->id,
                            'protokol_id' => $proto->id,
                        ],
                        [
                            'assigned_at' => now(),
                            'assigned_by' => $assignedByUserId,
                            'is_system_assigned' => true,
                            'score_snapshot' => $rec['score'],
                            'selection_reason' => $rec['reason'],
                        ]
                    );
                    $createdAssignments->push($penugasan);
                }
            }

            // Update status kegiatan menjadi scheduled jika kuota terpenuhi
            if ($createdAssignments->count() >= $kegiatan->jumlah_protokol) {
                $kegiatan->update(['status' => KegiatanStatus::SCHEDULED->value]);
            }

            return $createdAssignments;
        });
    }

    /**
     * Mengganti personil protokol bertugas secara manual disertai pencatatan audit log permanen.
     */
    public function overrideProtokol(
        Kegiatan $kegiatan,
        int $previousProtokolId,
        int $newProtokolId,
        string $reason,
        int $userId
    ): Penugasan {
        return DB::transaction(function () use ($kegiatan, $previousProtokolId, $newProtokolId, $reason, $userId) {
            // 1. Cari penugasan lama
            $penugasan = Penugasan::where('kegiatan_id', $kegiatan->id)
                ->where('protokol_id', $previousProtokolId)
                ->firstOrFail();

            // 2. Update penugasan ke protokol baru
            $penugasan->update([
                'protokol_id' => $newProtokolId,
                'is_override' => true,
                'override_reason' => $reason,
                'assigned_by' => $userId,
                'assigned_at' => now(),
            ]);

            // 3. Catat di tabel audit override log
            AuditOverrideLog::create([
                'penugasan_id' => $penugasan->id,
                'kegiatan_id' => $kegiatan->id,
                'previous_protokol_id' => $previousProtokolId,
                'new_protokol_id' => $newProtokolId,
                'overridden_by' => $userId,
                'reason' => $reason,
            ]);

            return $penugasan;
        });
    }
}
