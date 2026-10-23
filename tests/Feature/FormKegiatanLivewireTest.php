<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Livewire\Kegiatan\FormKegiatan;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Models\User;
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
        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat Koordinasi Keprotokolan',
            'detail' => 'Rapat internal protokol',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 11:00:00',
            'jumlah_protokol' => 1,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
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
        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat Awal',
            'detail' => 'Keterangan awal',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 11:00:00',
            'jumlah_protokol' => 1,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
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
        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Kegiatan Dihapus Dari Form',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 11:00:00',
            'jumlah_protokol' => 1,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
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

        $this->assertSoftDeleted('kegiatan', ['id' => $kegiatan->id]);
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

    /**
     * BUG-01: Direct Swap dan Add Manual wajib memvalidasi Hard Rules (bentrok jadwal / overlap).
     */
    public function test_swap_and_add_manual_reject_protocol_violating_hard_rules(): void
    {
        $this->actingAs($this->admin);

        // Buat kegiatan yang sedang berjalan untuk Andi di jam 09:00 - 12:00
        $tugasAndi = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Andi yang Berlangsung',
            'start_datetime' => '2026-10-25 09:00:00',
            'end_datetime' => '2026-10-25 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $tugasAndi->id,
            'protokol_id' => $this->andi->id,
            'assigned_at' => now(),
        ]);

        $test = Livewire::test(FormKegiatan::class)
            ->set('title', 'Tugas Baru Jam Beririsan')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 1)
            ->set('start_date', '2026-10-25')
            ->set('start_time', '10:00')
            ->set('end_date', '2026-10-25')
            ->set('end_time', '13:00')
            // Coba Add Manual Andi (yang sedang bertugas di jam 09:00 - 12:00)
            ->call('openAddManualModal')
            ->set('manualProtoId', $this->andi->id)
            ->set('manualReason', 'Paksa tambah Andi')
            ->call('addManualProtocol')
            ->assertHasErrors(['manualProtoId'])
            ->assertSet('showAddManualModal', true);

        // Tambahkan Dewi secara manual
        $test->set('manualProtoId', $this->dewi->id)
            ->set('manualReason', 'Dewi tersedia')
            ->call('addManualProtocol')
            ->assertSet('showAddManualModal', false)
            // Sekarang coba swap Dewi dengan Andi -> harus DITOLAK
            ->call('openSwapModal', $this->dewi->id)
            ->set('swapNewProtoId', $this->andi->id)
            ->set('swapReason', 'Coba swap ke Andi yang bentrok')
            ->call('applySwapProtocol')
            ->assertHasErrors(['swapNewProtoId'])
            ->assertSet('showSwapModal', true);
    }

    /**
     * BUG-03: Mencegah durasi minus / jam selesai lebih awal dari jam mulai di hari yang sama.
     */
    public function test_form_rejects_negative_or_inverted_duration_on_same_day(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class)
            ->set('title', 'Tugas Jam Minus')
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 1)
            ->set('start_date', '2026-10-25')
            ->set('start_time', '16:00')
            ->set('end_date', '2026-10-25')
            ->set('end_time', '09:00') // Inversi: selesai jam 09:00 tapi mulai 16:00
            ->call('runAutoSchedulerSimulation')
            ->assertHasErrors(['end_date']);
    }

    /**
     * BUG-05: Deteksi weekend pada dinas multi-hari (Jumat s.d. Minggu).
     */
    public function test_multiday_event_spanning_into_weekend_detects_weekend_and_offday_fee(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FormKegiatan::class)
            ->set('jenis_kegiatan_id', $this->jenisDalamKota->id)
            ->set('start_date', '2026-10-23') // Jumat (Hari kerja biasa)
            ->set('end_date', '2026-10-25')   // Minggu (Weekend)
            ->call('updateAutoDetection')
            ->assertSet('isWeekend', true)
            ->assertSet('detectedFee', 'Dibayar (Luar Hari Kerja)');
    }

    public function test_apply_swap_protocol_allows_gap_violation_but_rejects_direct_overlap(): void
    {
        $this->actingAs($this->admin);

        // Andi selesai bertugas 12 jam yang lalu (melanggar H1 / jeda 24 jam, tetapi TIDAK bentrok di jam tugas baru)
        $tugasKemarin = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Kemarin Malam',
            'start_datetime' => '2026-10-24 18:00:00',
            'end_datetime' => '2026-10-24 22:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $tugasKemarin->id,
            'protokol_id' => $this->andi->id,
            'assigned_at' => now(),
        ]);

        // Fajar memiliki tugas yang persis bersamaan dengan tugas baru (2026-10-25 10:00 - 12:00)
        $fajar = Protokol::create(['name' => 'Fajar Pratama', 'phone_wa' => '083333', 'status' => ProtokolStatus::ACTIVE]);
        $tugasBentrok = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Bersamaan',
            'start_datetime' => '2026-10-25 10:00:00',
            'end_datetime' => '2026-10-25 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $tugasBentrok->id,
            'protokol_id' => $fajar->id,
            'assigned_at' => now(),
        ]);

        $test = Livewire::test(FormKegiatan::class)
            ->set('title', 'Tugas Baru Hari Ini')
            ->set('jenis_kegiatan_id' , $this->jenisDalamKota->id)
            ->set('jumlah_protokol', 1)
            ->set('start_date', '2026-10-25')
            ->set('start_time', '10:00')
            ->set('end_date', '2026-10-25')
            ->set('end_time', '12:00');

        // Tambah Dewi ke list tugas
        $test->call('openAddManualModal')
            ->set('manualProtoId', $this->dewi->id)
            ->set('manualReason', 'Tugas awal')
            ->call('addManualProtocol')
            ->assertSet('showAddManualModal', false);

        // 1. Swap Dewi ke Andi (melanggar jeda 24 jam H1, tapi TIDAK bentrok jadwal langsung) -> HARUS DIIZINKAN
        $test->call('openSwapModal', $this->dewi->id)
            ->set('swapNewProtoId', $this->andi->id)
            ->set('swapReason', 'Disposisi khusus pimpinan')
            ->call('applySwapProtocol')
            ->assertHasNoErrors()
            ->assertSet('showSwapModal', false)
            ->assertSee('Andi Prasetyo');

        // 2. Swap Andi ke Fajar (bentrok waktu langsung di jam yang sama) -> HARUS DITOLAK
        $test->call('openSwapModal', $this->andi->id)
            ->set('swapNewProtoId', $fajar->id)
            ->set('swapReason', 'Coba ganti ke Fajar yang bentrok')
            ->call('applySwapProtocol')
            ->assertHasErrors(['swapNewProtoId'])
            ->assertSet('showSwapModal', true);
    }
}
