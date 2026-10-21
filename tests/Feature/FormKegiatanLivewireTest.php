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

    public function test_can_view_edit_form_kegiatan(): void
    {
        $kegiatan = \App\Models\Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat Koordinasi Keprotokolan',
            'detail' => 'Rapat internal protokol',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 11:00:00',
            'jumlah_protokol' => 1,
            'status' => 'scheduled',
        ]);

        \App\Models\Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => now(),
            'is_system_assigned' => true,
        ]);

        $this->actingAs($this->admin)
            ->get("/kegiatan/{$kegiatan->id}/edit")
            ->assertStatus(200)
            ->assertSee('Form Ubah Kegiatan & Penugasan Protokol')
            ->assertSee('Rapat Koordinasi Keprotokolan')
            ->assertSee('Dewi Kurnia');
    }

    public function test_can_update_existing_kegiatan_and_assignments(): void
    {
        $kegiatan = \App\Models\Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat Awal',
            'detail' => 'Keterangan awal',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 11:00:00',
            'jumlah_protokol' => 1,
            'status' => 'scheduled',
        ]);

        \App\Models\Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => now(),
            'is_system_assigned' => true,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class, ['kegiatan' => $kegiatan])
            ->assertSet('isEditing', true)
            ->assertSet('title', 'Rapat Awal')
            ->set('title', 'Rapat Koordinasi Revisi')
            ->set('detail', 'Keterangan diperbarui')
            ->call('confirmAndSave')
            ->assertRedirect(route('dashboard'));

        $kegiatan->refresh();
        $this->assertEquals('Rapat Koordinasi Revisi', $kegiatan->title);
        $this->assertEquals('Keterangan diperbarui', $kegiatan->detail);
    }

    public function test_can_delete_kegiatan_from_edit_form(): void
    {
        $kegiatan = \App\Models\Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Kegiatan Dihapus Dari Form',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 11:00:00',
            'jumlah_protokol' => 1,
            'status' => 'scheduled',
        ]);

        \App\Models\Penugasan::create([
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class, ['kegiatan' => $kegiatan])
            ->call('confirmDeleteKegiatan')
            ->assertSet('showDeleteModal', true)
            ->call('deleteKegiatan')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('kegiatan', ['id' => $kegiatan->id]);
        $this->assertDatabaseMissing('penugasan', ['kegiatan_id' => $kegiatan->id]);
    }

    public function test_can_swap_protocol_in_candidate_list(): void
    {
        $this->actingAs($this->admin);

        $fajar = Protokol::create(['name' => 'Fajar Pratama', 'phone_wa' => '089999', 'status' => ProtokolStatus::ACTIVE]);

        Livewire::test(FormKegiatan::class)
            ->set('title', 'Kunjungan Tamu')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 1)
            ->set('start_date', '2026-10-20')
            ->set('start_time', '08:30')
            ->set('end_date', '2026-10-20')
            ->set('end_time', '12:00')
            ->call('runAutoSchedulerSimulation')
            ->assertCount('recommendations', 1)
            // Ganti personil rekomendasi pertama dengan Fajar
            ->call('openSwapModal', $this->dewi->id)
            ->assertSet('showSwapModal', true)
            ->assertSet('swapOldProtoName', 'Dewi Kurnia')
            ->set('swapNewProtoId', $fajar->id)
            ->set('swapReason', 'Disposisi pimpinan untuk tugas khusus')
            ->call('applySwapProtocol')
            ->assertSet('showSwapModal', false)
            ->assertSee('Fajar Pratama')
            ->assertDontSee('Dewi Kurnia')
            ->call('confirmAndSave')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('penugasan', [
            'protokol_id' => $fajar->id,
        ]);
        $this->assertDatabaseMissing('penugasan', [
            'protokol_id' => $this->dewi->id,
        ]);
    }

    public function test_can_manually_add_and_remove_protocol(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class)
            ->set('title', 'Acara Manual')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 1)
            ->set('start_date', '2026-10-20')
            ->set('start_time', '08:30')
            ->set('end_date', '2026-10-20')
            ->set('end_time', '12:00')
            // Tambahkan personil secara manual tanpa menjalankan simulasi
            ->call('openAddManualModal')
            ->assertSet('showAddManualModal', true)
            ->set('manualProtoId', $this->andi->id)
            ->set('manualReason', 'Penugasan darurat')
            ->call('addManualProtocol')
            ->assertSet('showAddManualModal', false)
            ->assertCount('recommendations', 1)
            ->assertSee('Andi Prasetyo')
            // Hapus personil dari list
            ->call('removeProtocolFromList', $this->andi->id)
            ->assertCount('recommendations', 0);
    }
}
