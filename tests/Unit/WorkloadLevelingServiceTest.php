<?php

namespace Tests\Unit;

use App\Models\Protokol;
use App\Services\Scheduling\WorkloadLevelingService;
use Tests\TestCase;

class WorkloadLevelingServiceTest extends TestCase
{
    protected WorkloadLevelingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkloadLevelingService;
    }

    public function test_evaluates_overwork_when_tasks_exceed_mean_plus_two(): void
    {
        $budi = new Protokol([
            'name' => 'Budi Pratama',
        ]);
        $budi->total_tugas_bulan_ini = 7;
        $budi->tugas_kunker = 3;
        $budi->tugas_offday = 3;
        $budi->tugas_wfa = 1;

        $team = collect([$budi]);

        $eval = $this->service->evaluateProtokol($budi, 4.0, $team);

        $this->assertEquals(WorkloadLevelingService::LEVEL_OVERWORK, $eval['level']);
        $this->assertEquals('Overwork', $eval['label']);
        $this->assertEquals('rose', $eval['badge']);
        $this->assertStringContainsString('Beban > Rata-rata (+3)', $eval['diagnosa']);
    }

    public function test_evaluates_harus_ditugaskan_when_zero_tasks(): void
    {
        $rian = new Protokol([
            'name' => 'Rian Hidayat',
        ]);
        $rian->total_tugas_bulan_ini = 0;
        $rian->tugas_kunker = 0;
        $rian->tugas_offday = 0;
        $rian->tugas_wfa = 0;

        $team = collect([$rian]);

        $eval = $this->service->evaluateProtokol($rian, 4.0, $team);

        $this->assertEquals(WorkloadLevelingService::LEVEL_HARUS_DITUGASKAN, $eval['level']);
        $this->assertEquals('Harus Ditugaskan', $eval['label']);
        $this->assertEquals('amber', $eval['badge']);
        $this->assertStringContainsString('Nol Tugas (Prioritas Mutlak)', $eval['diagnosa']);
    }

    public function test_evaluates_harus_ditugaskan_when_zero_kunker_and_team_has_kunker(): void
    {
        $dewi = new Protokol([
            'name' => 'Dewi Lestari',
        ]);
        $dewi->total_tugas_bulan_ini = 4;
        $dewi->tugas_kunker = 0;
        $dewi->tugas_dibayar_total = 2;
        $dewi->tugas_offday = 1;
        $dewi->tugas_wfa = 1;

        $budi = new Protokol(['name' => 'Budi']);
        $budi->tugas_kunker = 2;

        $team = collect([$dewi, $budi]);

        $eval = $this->service->evaluateProtokol($dewi, 4.0, $team);

        $this->assertEquals(WorkloadLevelingService::LEVEL_HARUS_DITUGASKAN, $eval['level']);
        $this->assertStringContainsString('Belum Pernah Kunker (0x)', $eval['diagnosa']);
    }

    public function test_evaluates_seimbang_when_proportional(): void
    {
        $siti = new Protokol([
            'name' => 'Siti Rahma',
        ]);
        $siti->total_tugas_bulan_ini = 4;
        $siti->tugas_kunker = 1;
        $siti->tugas_dibayar_total = 3;
        $siti->tugas_offday = 1;
        $siti->tugas_wfa = 1;

        $team = collect([$siti]);

        $eval = $this->service->evaluateProtokol($siti, 4.0, $team);

        $this->assertEquals(WorkloadLevelingService::LEVEL_SEIMBANG, $eval['level']);
        $this->assertEquals('Seimbang', $eval['label']);
        $this->assertEquals('emerald', $eval['badge']);
    }

    public function test_evaluates_team_fairness_simulation_case_from_prd_v2(): void
    {
        // 5 Protokol Aktif sesuai Simulasi PRD 7.4
        // Budi (7x, 3 kunker, 3 weekend) -> Overwork
        // Siti (4x, 1 kunker, 1 weekend) -> Seimbang
        // Ahmad (4x, 1 kunker, 1 weekend) -> Seimbang
        // Dewi (4x, 0 kunker, 1 weekend) -> Harus Ditugaskan
        // Rian (1x, 0 kunker, 0 weekend) -> Harus Ditugaskan
        $budi = new Protokol(['id' => 1, 'name' => 'Budi Pratama']);
        $budi->total_tugas_bulan_ini = 7;
        $budi->tugas_kunker = 3;
        $budi->tugas_offday = 3;
        $budi->tugas_wfa = 1;
        $budi->tugas_dibayar_total = 5;

        $siti = new Protokol(['id' => 2, 'name' => 'Siti Rahma']);
        $siti->total_tugas_bulan_ini = 4;
        $siti->tugas_kunker = 1;
        $siti->tugas_offday = 1;
        $siti->tugas_wfa = 1;
        $siti->tugas_dibayar_total = 3;

        $ahmad = new Protokol(['id' => 3, 'name' => 'Ahmad Fauzi']);
        $ahmad->total_tugas_bulan_ini = 4;
        $ahmad->tugas_kunker = 1;
        $ahmad->tugas_offday = 1;
        $ahmad->tugas_wfa = 1;
        $ahmad->tugas_dibayar_total = 2;

        $dewi = new Protokol(['id' => 4, 'name' => 'Dewi Lestari']);
        $dewi->total_tugas_bulan_ini = 4;
        $dewi->tugas_kunker = 0;
        $dewi->tugas_offday = 1;
        $dewi->tugas_wfa = 1;
        $dewi->tugas_dibayar_total = 1;

        $rian = new Protokol(['id' => 5, 'name' => 'Rian Hidayat']);
        $rian->total_tugas_bulan_ini = 1;
        $rian->tugas_kunker = 0;
        $rian->tugas_offday = 0;
        $rian->tugas_wfa = 0;
        $rian->tugas_dibayar_total = 0;

        $team = collect([$budi, $siti, $ahmad, $dewi, $rian]);

        $result = $this->service->evaluateTeam($team);

        // Fairness Gap: 7 - 1 = 6
        $this->assertEquals(6, $result['fairness_gap']);
        // Indeks: max(60, 100 - (6 * 10)) = 60%
        $this->assertEquals(60, $result['indeks']);
        $this->assertEquals(WorkloadLevelingService::TEAM_STATUS_PERLU_PEMERATAAN, $result['status']);
        $this->assertEquals('Perlu Pemerataan (Tidak Seimbang)', $result['status_label']);

        // Counts: 1 Overwork, 2 Seimbang, 2 Harus Ditugaskan
        $this->assertEquals(1, $result['counts'][WorkloadLevelingService::LEVEL_OVERWORK]);
        $this->assertEquals(2, $result['counts'][WorkloadLevelingService::LEVEL_SEIMBANG]);
        $this->assertEquals(2, $result['counts'][WorkloadLevelingService::LEVEL_HARUS_DITUGASKAN]);

        $this->assertStringContainsString('1 Overwork | 2 Seimbang | 2 Harus Ditugaskan', $result['summary_text']);
        $this->assertStringContainsString('Budi Pratama', $result['diagnosa_text']);
    }
}
