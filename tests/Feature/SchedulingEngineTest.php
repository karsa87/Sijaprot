<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Models\AuditOverrideLog;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected SchedulingEngine $engine;
    protected User $admin;
    protected JenisKegiatan $jenisDalamKota;
    protected JenisKegiatan $jenisLuarKota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(SchedulingEngine::class);

        $this->admin = User::create([
            'name' => 'Budi Santoso',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $this->jenisDalamKota = JenisKegiatan::create([
            'name' => 'Penyambutan Dubes',
            'code' => 'VIP_DALAM',
            'payment_type' => 'paid',
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->jenisLuarKota = JenisKegiatan::create([
            'name' => 'Dinas Surabaya',
            'code' => 'KUNKER_SURABAYA',
            'payment_type' => 'paid',
            'location_type' => LocationType::LUAR_KOTA->value,
        ]);
    }

    public function test_engine_recommends_and_assigns_protocols_fairly(): void
    {
        // Buat 4 protokol
        $proto1 = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '08111111', 'status' => ProtokolStatus::ACTIVE]);
        $proto2 = Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '08222222', 'status' => ProtokolStatus::ACTIVE]);
        $proto3 = Protokol::create(['name' => 'Fajar Hidayat', 'phone_wa' => '08333333', 'status' => ProtokolStatus::ACTIVE]);
        $protoInactive = Protokol::create(['name' => 'Siti Nonaktif', 'phone_wa' => '08444444', 'status' => ProtokolStatus::INACTIVE]);

        // Buat kegiatan yang membutuhkan 2 orang
        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Penyambutan Dubes Finlandia',
            'start_datetime' => '2026-10-20 08:30:00',
            'end_datetime' => '2026-10-20 12:00:00',
            'jumlah_protokol' => 2,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::DRAFT->value,
            'created_by' => $this->admin->id,
        ]);

        // 1. Uji Rekomendasi (Preview)
        $recommendation = $this->engine->generateRecommendation($kegiatan);

        $this->assertEquals(2, $recommendation['recommendations']->count());
        $this->assertEquals('strict', $recommendation['relaxation_level']);
        $this->assertNull($recommendation['warning']);

        // Protokol nonaktif tidak boleh masuk rekomendasi
        $recommendedIds = $recommendation['recommendations']->pluck('protokol.id');
        $this->assertNotContains($protoInactive->id, $recommendedIds);

        // 2. Uji Commit Penugasan
        $assigned = $this->engine->assignAndCommit($kegiatan, null, $this->admin->id);

        $this->assertCount(2, $assigned);
        $this->assertDatabaseHas('kegiatan', [
            'id' => $kegiatan->id,
            'status' => KegiatanStatus::SCHEDULED->value,
        ]);

        $this->assertDatabaseCount('penugasan', 2);
    }

    public function test_override_protokol_creates_audit_log_record(): void
    {
        $dewi = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '08111111', 'status' => ProtokolStatus::ACTIVE]);
        $andi = Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '08222222', 'status' => ProtokolStatus::ACTIVE]);

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Upacara Kenegaraan',
            'start_datetime' => '2026-10-20 08:00:00',
            'end_datetime' => '2026-10-20 11:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::SCHEDULED->value,
        ]);

        // Awalnya Dewi ditugaskan
        $penugasan = Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $dewi->id,
            'is_system_assigned' => true,
        ]);

        // Dewi sakit/berhalangan -> Admin override ganti ke Andi
        $reason = 'Dewi Kurnia izin sakit dengan surat dokter.';
        $this->engine->overrideProtokol($kegiatan, $dewi->id, $andi->id, $reason, $this->admin->id);

        // Verifikasi penugasan terupdate
        $penugasan->refresh();
        $this->assertEquals($andi->id, $penugasan->protokol_id);
        $this->assertTrue($penugasan->is_override);
        $this->assertEquals($reason, $penugasan->override_reason);

        // Verifikasi log audit tersimpan di tabel audit_override_logs
        $this->assertDatabaseHas('audit_override_logs', [
            'penugasan_id' => $penugasan->id,
            'kegiatan_id' => $kegiatan->id,
            'previous_protokol_id' => $dewi->id,
            'new_protokol_id' => $andi->id,
            'overridden_by' => $this->admin->id,
            'reason' => $reason,
        ]);
    }

    public function test_multi_day_luar_kota_recorded_as_single_assignment_with_full_calendar_gap(): void
    {
        $dewi = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '08111111', 'status' => ProtokolStatus::ACTIVE]);

        // Multi-hari luar kota (3 hari: 10 - 12 Oktober 2026)
        $trip3Hari = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisLuarKota->id,
            'title' => 'Pendampingan Kunker Surabaya 3 Hari',
            'start_datetime' => '2026-10-10 08:00:00',
            'end_datetime' => '2026-10-12 18:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::LUAR_KOTA->value,
            'status' => KegiatanStatus::DRAFT->value,
        ]);

        // Ditugaskan
        $this->engine->assignAndCommit($trip3Hari, [$dewi->id], $this->admin->id);

        // Opsi A: Dihitung sebagai TEPAT 1 penugasan di tabel penugasan
        $this->assertDatabaseCount('penugasan', 1);

        // Verifikasi ketersediaan: 13 Oktober (H+1 hari kalender dari kepulangan 12 Okt) tidak boleh bertugas
        $kegiatan13Okt = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat 13 Okt',
            'start_datetime' => '2026-10-13 09:00:00',
            'end_datetime' => '2026-10-13 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $gapChecker = app(\App\Services\Scheduling\GapRuleChecker::class);
        $this->assertFalse($gapChecker->passesAllHardRules($dewi, $kegiatan13Okt));

        // 14 Oktober (H+2) Dewi sudah bebas bertugas kembali
        $kegiatan14Okt = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat 14 Okt',
            'start_datetime' => '2026-10-14 09:00:00',
            'end_datetime' => '2026-10-14 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);
        $this->assertTrue($gapChecker->passesAllHardRules($dewi, $kegiatan14Okt));
    }
}
