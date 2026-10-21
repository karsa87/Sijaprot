<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard\KalenderMonitoring;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KalenderMonitoringLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Protokol $dewi;

    protected JenisKegiatan $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::create([
            'name' => 'Budi Santoso',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $this->dewi = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '081234567890',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->jenis = JenisKegiatan::create([
            'name' => 'Kunjungan Tamu VIP',
            'code' => 'VIP',
            'payment_type' => 'paid',
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);
    }

    public function test_can_view_dashboard_and_render_calendar(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('Kegiatan Bulan Ini')
            ->assertSee('Protokol Siap Tugas')
            ->assertSee('Indeks Keseimbangan');
    }

    public function test_selecting_date_displays_activities_and_assigned_protocols(): void
    {
        $targetDate = Carbon::now()->startOfMonth()->addDays(4); // Tanggal 5

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Penyambutan Dubes UNESCO',
            'start_datetime' => $targetDate->copy()->setTime(9, 0),
            'end_datetime' => $targetDate->copy()->setTime(12, 0),
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::SCHEDULED->value,
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(KalenderMonitoring::class)
            ->call('selectDate', 5)
            ->assertSet('selectedDay', 5)
            ->assertSee('Penyambutan Dubes UNESCO')
            ->assertSee('Dewi Kurnia');
    }

    public function test_month_navigation_works(): void
    {
        $this->actingAs($this->admin);

        $currentMonth = Carbon::now()->month;

        Livewire::test(KalenderMonitoring::class)
            ->call('nextMonth')
            ->assertSet('selectedDay', 1)
            ->call('prevMonth')
            ->assertSet('month', $currentMonth);
    }

    public function test_can_delete_kegiatan_and_cascade_assignments(): void
    {
        $targetDate = Carbon::now()->startOfMonth()->addDays(4); // Tanggal 5

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenis->id,
            'title' => 'Acara Akan Dihapus',
            'start_datetime' => $targetDate->copy()->setTime(10, 0),
            'end_datetime' => $targetDate->copy()->setTime(12, 0),
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::SCHEDULED->value,
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(KalenderMonitoring::class)
            ->call('confirmDeleteKegiatan', $kegiatan->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingKegiatanTitle', 'Acara Akan Dihapus')
            ->call('deleteKegiatan')
            ->assertSet('showDeleteModal', false);

        $this->assertSoftDeleted('kegiatan', ['id' => $kegiatan->id]);
        $this->assertDatabaseMissing('penugasan', ['kegiatan_id' => $kegiatan->id]);
    }
}
