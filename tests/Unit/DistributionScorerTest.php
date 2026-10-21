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
}
