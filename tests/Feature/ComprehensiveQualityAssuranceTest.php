<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessAutoSchedulingJob;
use App\Livewire\Kegiatan\FormKegiatan;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Models\User;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ComprehensiveQualityAssuranceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected JenisKegiatan $jenisDalamKota;

    protected JenisKegiatan $jenisLuarKota;

    protected Protokol $dewi;

    protected Protokol $andi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::create([
            'name' => 'Kasubbag Protokol',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $this->operator = User::create([
            'name' => 'Operator Staff',
            'email' => 'operator@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::OPERATOR,
        ]);

        $this->dewi = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '081234567890',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->andi = Protokol::create([
            'name' => 'Andi Prasetyo',
            'phone_wa' => '081298765432',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->jenisDalamKota = JenisKegiatan::create([
            'name' => 'Kunjungan Tamu VIP',
            'code' => 'VIP_DALAM',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => LocationType::DALAM_KOTA->value,
            'estimated_fee' => 350000.00,
        ]);

        $this->jenisLuarKota = JenisKegiatan::create([
            'name' => 'Kunker Luar Kota',
            'code' => 'KUNKER_LK',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => LocationType::LUAR_KOTA->value,
            'estimated_fee' => 750000.00,
        ]);
    }

    /**
     * METODE 1: TEORI MOCKING
     * - Menguji time-travel kalender dengan Carbon::setTestNow()
     * - Menguji asynchronous queue dispatching dengan Queue::fake()
     */
    public function test_mock_time_travel_and_queue_dispatch(): void
    {
        // 1. Mock Time: Pindahkan waktu sistem ke Sabtu, 24 Oktober 2026 jam 08:00
        Carbon::setTestNow(Carbon::parse('2026-10-24 08:00:00'));

        $this->assertTrue(Carbon::now()->isWeekend());
        $this->assertEquals('Saturday', Carbon::now()->englishDayOfWeek);

        // 2. Mock Queue: Cegah job dieksekusi secara nyata, verifikasi dispatching
        Queue::fake();

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Upacara Hari Sabtu',
            'start_datetime' => Carbon::now(),
            'end_datetime' => Carbon::now()->addHours(4),
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::DRAFT->value,
            'created_by' => $this->admin->id,
        ]);

        ProcessAutoSchedulingJob::dispatch($kegiatan, $this->admin->id);

        Queue::assertPushed(ProcessAutoSchedulingJob::class, function ($job) use ($kegiatan) {
            return $job->kegiatan->id === $kegiatan->id;
        });

        // Reset waktu kembali normal
        Carbon::setTestNow();
    }

    /**
     * METODE 2: REAL API / HTTP & LIVEWIRE COMPONENT HIT
     * - Menguji pemanggilan request HTTP nyata ke controller ekspor
     * - Menguji interaksi komponen Livewire secara end-to-end (Real Hit)
     */
    public function test_real_http_hit_and_livewire_execution(): void
    {
        // 1. Real HTTP Hit ke Endpoint Export Excel
        $response = $this->actingAs($this->admin)
            ->get('/laporan/export/excel?month=10&year=2026');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=Rekap_Distribusi_Protokol_10_2026.xlsx');

        // 2. Real Livewire Component Hit
        Livewire::actingAs($this->admin)
            ->test(FormKegiatan::class)
            ->set('title', 'Kunjungan Kerja Real Hit Test')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 2)
            ->set('start_date', '2026-10-28')
            ->set('start_time', '09:00')
            ->set('end_date', '2026-10-28')
            ->set('end_time', '12:00')
            ->call('runAutoSchedulerSimulation')
            ->assertCount('recommendations', 2)
            ->call('confirmAndSave')
            ->assertRedirect(route('dashboard'));
    }

    /**
     * METODE 3: REAL DATABASE ASSERTION
     * - Menguji konsistensi state PostgreSQL pada tabel kegiatan, penugasan, dan audit_override_logs
     * - Memverifikasi cascade integrity dan integritas relasi
     */
    public function test_real_database_assertions_and_audit_integrity(): void
    {
        $engine = app(SchedulingEngine::class);

        // Buat kegiatan dengan status scheduled
        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Gala Dinner Protokol',
            'start_datetime' => '2026-10-25 19:00:00',
            'end_datetime' => '2026-10-25 21:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::SCHEDULED->value,
            'created_by' => $this->admin->id,
        ]);

        // 1. Database Assertion: Buat Penugasan Awal untuk Dewi
        $penugasan = Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
            'is_system_assigned' => true,
        ]);

        $this->assertDatabaseHas('penugasan', [
            'id' => $penugasan->id,
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'is_system_assigned' => true,
            'is_override' => false,
        ]);

        // 2. Database Assertion: Override Dewi digantikan Andi
        $reason = 'Dewi Kurnia sakit demam.';
        $engine->overrideProtokol($kegiatan, $this->dewi->id, $this->andi->id, $reason, $this->admin->id);

        $this->assertDatabaseHas('penugasan', [
            'id' => $penugasan->id,
            'protokol_id' => $this->andi->id,
            'is_override' => true,
            'override_reason' => $reason,
        ]);

        $this->assertDatabaseHas('audit_override_logs', [
            'kegiatan_id' => $kegiatan->id,
            'previous_protokol_id' => $this->dewi->id,
            'new_protokol_id' => $this->andi->id,
            'overridden_by' => $this->admin->id,
            'reason' => $reason,
        ]);

        // 3. Database Assertion: Soft Deletes pada Protokol
        $this->dewi->delete();
        $this->assertSoftDeleted('protokols', ['id' => $this->dewi->id]);
    }
}
