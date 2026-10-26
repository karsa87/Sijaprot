<?php

namespace Tests\Unit;

use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Services\Scheduling\DistributionScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionScorerTest extends TestCase
{
    use RefreshDatabase;

    protected DistributionScorer $scorer;

    protected Protokol $dewi;

    protected Protokol $andi;

    protected JenisKegiatan $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new DistributionScorer;

        $this->dewi = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '081234567890',
            'status' => 'active',
        ]);

        $this->andi = Protokol::create([
            'name' => 'Andi Prasetyo',
            'phone_wa' => '081298765432',
            'status' => 'active',
        ]);

        $this->jenis = JenisKegiatan::create([
            'name' => 'Kegiatan Biasa',
            'code' => 'BIASA',
            'payment_type' => 'paid',
            'location_type' => 'dalam_kota',
        ]);
    }

    public function test_protocol_with_zero_tasks_this_month_receives_50_point_boost(): void
    {
        // Dewi sudah bertugas 1x di bulan Oktober
        $kegiatanOkt = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Tugas Dewi',
            'start_datetime' => '2026-10-05 09:00:00',
            'end_datetime' => '2026-10-05 12:00:00',
            'status' => 'scheduled',
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatanOkt->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-05 09:00:00',
        ]);

        // Andi belum pernah bertugas di bulan Oktober (0 task)
        $kegiatanTarget = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Tugas Baru',
            'start_datetime' => '2026-10-20 09:00:00',
            'end_datetime' => '2026-10-20 12:00:00',
        ]);

        $activePool = collect([$this->dewi, $this->andi]);
        $this->scorer->computeTeamAverages($activePool, 10, 2026);

        $scoreDewi = $this->scorer->calculateScore($this->dewi, $kegiatanTarget);
        $scoreAndi = $this->scorer->calculateScore($this->andi, $kegiatanTarget);

        // Andi harus mendapat skor boost W1 = 50.0
        $this->assertEquals(50.0, $scoreAndi['breakdown']['min_month']);
        $this->assertEquals(0.0, $scoreDewi['breakdown']['min_month']);

        // Total skor Andi harus lebih tinggi dari Dewi
        $this->assertGreaterThan($scoreDewi['score'], $scoreAndi['score']);
        $this->assertStringContainsString('Belum pernah bertugas bulan ini', $scoreAndi['reason']);
    }

    public function test_kunker_target_balance_grants_40_point_boost_for_protocol_with_zero_kunker(): void
    {
        // Dewi sudah pernah Kunker 1x di bulan Oktober
        $kegiatanKunkerDewi = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Kunker Bali Dewi',
            'start_datetime' => '2026-10-02 09:00:00',
            'end_datetime' => '2026-10-02 17:00:00',
            'is_kunker' => true,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $kegiatanKunkerDewi->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-02 09:00:00',
        ]);

        // Andi sudah bertugas 1x tapi bukan Kunker (tugas biasa)
        $kegiatanBiasaAndi = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Rapat Internal Andi',
            'start_datetime' => '2026-10-03 09:00:00',
            'end_datetime' => '2026-10-03 12:00:00',
            'is_kunker' => false,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $kegiatanBiasaAndi->id,
            'protokol_id' => $this->andi->id,
            'assigned_at' => '2026-10-03 09:00:00',
        ]);

        // Kegiatan baru bertipe KUNKER
        $kegiatanKunkerBaru = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Kunjungan Kerja DPR ke Solo',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 18:00:00',
            'is_kunker' => true,
        ]);

        $activePool = collect([$this->dewi, $this->andi]);
        $this->scorer->computeTeamAverages($activePool, 10, 2026);

        $scoreDewi = $this->scorer->calculateScore($this->dewi, $kegiatanKunkerBaru);
        $scoreAndi = $this->scorer->calculateScore($this->andi, $kegiatanKunkerBaru);

        // Andi (0 kunker) harus memperoleh boost +40.0 poin
        $this->assertEquals(40.0, $scoreAndi['breakdown']['kunker_boost']);
        $this->assertEquals(0.0, $scoreDewi['breakdown']['kunker_boost']);
        $this->assertStringContainsString('Target Kunker: Belum pernah Kunker bulan ini (+40 poin)', $scoreAndi['reason']);

        // Skor Andi harus lebih tinggi dari Dewi pada kegiatan Kunker
        $this->assertGreaterThan($scoreDewi['score'], $scoreAndi['score']);
    }

    public function test_overwork_penalty_applies_when_weekend_duties_reach_threshold(): void
    {
        // Beri Dewi 3 tugas weekend di bulan Oktober
        for ($i = 1; $i <= 3; $i++) {
            $keg = Kegiatan::create([
                'jenis_kegiatan_id' => $this->jenis->id,
                'title' => "Tugas Weekend {$i}",
                'start_datetime' => "2026-10-0{$i} 09:00:00",
                'end_datetime' => "2026-10-0{$i} 12:00:00",
                'is_weekend' => true,
                'status' => 'scheduled',
            ]);
            Penugasan::create([
                'kegiatan_id' => $keg->id,
                'protokol_id' => $this->dewi->id,
                'assigned_at' => "2026-10-0{$i} 09:00:00",
            ]);
        }

        $activePool = collect([$this->dewi, $this->andi]);
        $this->scorer->computeTeamAverages($activePool, 10, 2026);

        $kegiatanBaru = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Tugas Tambahan',
            'start_datetime' => '2026-10-28 09:00:00',
            'end_datetime' => '2026-10-28 12:00:00',
        ]);

        $scoreDewi = $this->scorer->calculateScore($this->dewi, $kegiatanBaru);

        $this->assertEquals(-40.0, $scoreDewi['breakdown']['overwork_penalty']);
        $this->assertStringContainsString('Status Overwork', $scoreDewi['reason']);
    }
}
