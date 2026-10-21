<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Livewire\Master\DaftarProtokol;
use App\Livewire\Master\MasterReferensi;
use App\Models\AuditOverrideLog;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityAndAuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected JenisKegiatan $jenis;

    protected Protokol $protokolA;

    protected Protokol $protokolB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::create([
            'name' => 'Admin Protokol',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $this->operator = User::create([
            'name' => 'Operator Lapangan',
            'email' => 'operator@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::OPERATOR,
        ]);

        $this->jenis = JenisKegiatan::create([
            'name' => 'Audiensi Menteri',
            'code' => 'AUD_MENTERI',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => LocationType::DALAM_KOTA->value,
            'estimated_fee' => 300000,
        ]);

        $this->protokolA = Protokol::create([
            'name' => 'Rahmat Hidayat',
            'phone_wa' => '081234567891',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->protokolB = Protokol::create([
            'name' => 'Siti Nurhaliza',
            'phone_wa' => '081234567892',
            'status' => ProtokolStatus::ACTIVE,
        ]);
    }

    /**
     * SEC-01: Operator tidak boleh dapat mengakses route master & laporan (HTTP 403).
     */
    public function test_operator_is_forbidden_from_master_and_laporan_routes(): void
    {
        $this->actingAs($this->operator)
            ->get('/master/protokol')
            ->assertForbidden();

        $this->actingAs($this->operator)
            ->get('/master/referensi')
            ->assertForbidden();

        $this->actingAs($this->operator)
            ->get('/laporan/distribusi')
            ->assertForbidden();

        $this->actingAs($this->operator)
            ->get('/laporan/export/excel')
            ->assertForbidden();

        $this->actingAs($this->operator)
            ->get('/laporan/export/pdf')
            ->assertForbidden();
    }

    /**
     * SEC-01: Operator tidak boleh dapat menjalankan komponen Livewire Master secara langsung (BFLA Defense).
     */
    public function test_operator_cannot_instantiate_master_livewire_components(): void
    {
        Livewire::actingAs($this->operator)
            ->test(DaftarProtokol::class)
            ->assertStatus(403);

        Livewire::actingAs($this->operator)
            ->test(MasterReferensi::class)
            ->assertStatus(403);
    }

    /**
     * SEC-01: Admin Protokol dapat mengakses route master dan laporan dengan sukses (HTTP 200).
     */
    public function test_admin_protokol_can_access_master_and_laporan(): void
    {
        $this->actingAs($this->admin)
            ->get('/master/protokol')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/master/referensi')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/laporan/distribusi')
            ->assertOk();
    }

    /**
     * SEC-02: Penghapusan kegiatan melakukan Soft Delete dan menjamin jejak Audit Log tetap utuh permanen.
     */
    public function test_deleting_kegiatan_preserves_audit_override_logs_permanently(): void
    {
        $engine = app(SchedulingEngine::class);

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Kunjungan Kerja Luar Negeri',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::SCHEDULED->value,
            'created_by' => $this->admin->id,
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->protokolA->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Catat override penugasan (menghasilkan audit_override_logs)
        $engine->overrideProtokol(
            $kegiatan,
            $this->protokolA->id,
            $this->protokolB->id,
            'Petugas A berhalangan hadir mendadak',
            $this->admin->id
        );

        $this->assertDatabaseHas('audit_override_logs', [
            'kegiatan_id' => $kegiatan->id,
            'previous_protokol_id' => $this->protokolA->id,
            'new_protokol_id' => $this->protokolB->id,
        ]);

        // Hapus kegiatan (Soft Delete)
        $kegiatan->delete();

        // 1. Verifikasi kegiatan ter-soft delete (kolom deleted_at terisi)
        $this->assertSoftDeleted('kegiatan', ['id' => $kegiatan->id]);

        // 2. Verifikasi jejak audit log TIDAK terhapus sama sekali (Audit Trail Preservation)
        $this->assertDatabaseHas('audit_override_logs', [
            'kegiatan_id' => $kegiatan->id,
            'previous_protokol_id' => $this->protokolA->id,
            'new_protokol_id' => $this->protokolB->id,
        ]);

        // 3. Verifikasi relasi kegiatan dapat diakses via withTrashed()
        $auditLog = AuditOverrideLog::where('kegiatan_id', $kegiatan->id)->first();
        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->kegiatan);
        $this->assertEquals('Kunjungan Kerja Luar Negeri', $auditLog->kegiatan->title);
    }

    /**
     * SEC-04: Endpoint POST /login diproteksi oleh rate limiter throttle:5,1.
     */
    public function test_login_endpoint_has_rate_limiting(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => 'wrong@kemdikbud.go.id',
                'password' => 'wrongpass',
            ]);
            $this->assertNotEquals(429, $response->status());
        }

        // Percobaan ke-6 dalam 1 menit harus menerima 429 Too Many Requests
        $response = $this->post('/login', [
            'email' => 'wrong@kemdikbud.go.id',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(429);
    }
}
