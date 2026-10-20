<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Livewire\Kegiatan\FormKegiatan;
use App\Models\JenisKegiatan;
use App\Models\Protokol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormKegiatanLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected JenisKegiatan $jenisDalamKota;
    protected JenisKegiatan $jenisLuarKota;
    protected Protokol $dewi;
    protected Protokol $andi;

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

        $this->dewi = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);
        $this->andi = Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '082222', 'status' => ProtokolStatus::ACTIVE]);

        $this->jenisDalamKota = JenisKegiatan::create([
            'name' => 'Kunjungan Tamu VIP',
            'code' => 'VIP',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->jenisLuarKota = JenisKegiatan::create([
            'name' => 'Kunker Luar Kota',
            'code' => 'LK',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => LocationType::LUAR_KOTA->value,
        ]);
    }

    public function test_can_view_form_kegiatan(): void
    {
        $this->actingAs($this->admin)
            ->get('/kegiatan/baru')
            ->assertStatus(200)
            ->assertSee('Form Buat Kegiatan', false)
            ->assertSee('Jalankan Algoritma Penjadwalan Otomatis');
    }

    public function test_auto_detection_updates_on_date_or_type_change(): void
    {
        $this->actingAs($this->admin);

        // Pilih hari Sabtu (2026-10-24 adalah hari Sabtu)
        Livewire::test(FormKegiatan::class)
            ->set('start_date', '2026-10-24')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->assertSet('isWeekend', true)
            ->assertSee('Sabtu (Weekend)')
            ->assertSee('Dibayar (Luar Hari Kerja)');
    }

    public function test_run_simulation_produces_recommendations(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class)
            ->set('title', 'Penyambutan Dubes Finlandia')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 2)
            ->set('start_date', '2026-10-20')
            ->set('start_time', '08:30')
            ->set('end_date', '2026-10-20')
            ->set('end_time', '12:00')
            ->call('runAutoSchedulerSimulation')
            ->assertSet('hasRunSimulation', true)
            ->assertCount('recommendations', 2)
            ->assertSee('Skor:')
            ->assertSee('Hard Rules: ✅ Lolos');
    }

    public function test_confirm_and_save_creates_kegiatan_and_assignments(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class)
            ->set('title', 'Penyambutan Dubes Finlandia')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 2)
            ->set('start_date', '2026-10-20')
            ->set('start_time', '08:30')
            ->set('end_date', '2026-10-20')
            ->set('end_time', '12:00')
            ->call('runAutoSchedulerSimulation')
            ->call('confirmAndSave')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('kegiatan', [
            'title' => 'Penyambutan Dubes Finlandia',
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseCount('penugasan', 2);
    }
}
