<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Master\MasterReferensi;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterReferensiLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Budi Santoso',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);
    }

    public function test_can_view_master_referensi(): void
    {
        $this->withoutVite();

        JenisKegiatan::create([
            'name' => 'Kunjungan Tamu VIP',
            'code' => 'VIP_TEST',
            'payment_type' => 'paid',
            'location_type' => 'dalam_kota',
        ]);

        HariLibur::create([
            'tanggal' => '2026-08-17',
            'tahun' => 2026,
            'name' => 'Hari Kemerdekaan RI',
            'type' => 'nasional',
        ]);

        $this->actingAs($this->admin)
            ->get('/master/referensi')
            ->assertStatus(200)
            ->assertSee('Kunjungan Tamu VIP')
            ->assertSee('Hari Kemerdekaan RI');
    }

    public function test_can_create_and_update_jenis_kegiatan(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin);

        Livewire::test(MasterReferensi::class)
            ->call('openCreateJenisModal')
            ->set('jenisName', 'Penyambutan Dubes')
            ->set('jenisCode', 'DUBES_BARU')
            ->set('jenisPaymentType', 'paid')
            ->set('jenisPaymentDayType', 'workday')
            ->set('jenisLocationType', 'dalam_kota')
            ->set('jenisEstimatedFee', 300000)
            ->call('saveJenisKegiatan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jenis_kegiatan', [
            'code' => 'DUBES_BARU',
            'name' => 'Penyambutan Dubes',
        ]);
    }

    public function test_can_create_and_delete_hari_libur(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin);

        Livewire::test(MasterReferensi::class)
            ->call('openCreateLiburModal')
            ->set('liburTanggal', '2026-10-28')
            ->set('liburName', 'Hari Sumpah Pemuda')
            ->set('liburType', 'hari_besar')
            ->call('saveHariLibur')
            ->assertHasNoErrors();

        $all = HariLibur::all();
        $this->assertNotEmpty($all, 'HariLibur table is empty after saveHariLibur: '.json_encode($all));
        $libur = HariLibur::whereDate('tanggal', '2026-10-28')->first();
        $this->assertNotNull($libur);

        // Delete test
        Livewire::test(MasterReferensi::class)
            ->call('deleteHariLibur', $libur->id);

        $this->assertDatabaseMissing('hari_libur', ['tanggal' => '2026-10-28']);
    }

    /**
     * BUG-07: Batasan estimasi honorarium untuk mencegah numeric overflow PostgreSQL.
     */
    public function test_cannot_input_estimated_fee_exceeding_threshold(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin);

        Livewire::test(MasterReferensi::class)
            ->call('openCreateJenisModal')
            ->set('jenisName', 'Kegiatan Ekstrem')
            ->set('jenisCode', 'EKSTREM')
            ->set('jenisPaymentType', 'paid')
            ->set('jenisLocationType', 'dalam_kota')
            ->set('jenisEstimatedFee', 10000000000) // 10 miliar
            ->call('saveJenisKegiatan')
            ->assertHasErrors(['jenisEstimatedFee']);
    }
}
