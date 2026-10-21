<?php

namespace Tests\Feature;

use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Livewire\Master\DaftarProtokol;
use App\Models\Protokol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterProtokolLivewireTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/master/protokol');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_daftar_protokol(): void
    {
        $this->withoutVite();

        $proto = Protokol::create([
            'name' => 'Dewi Kurnia, S.AP',
            'phone_wa' => '081234567890',
            'nip' => '199203152018012001',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get('/master/protokol')
            ->assertStatus(200)
            ->assertSee('Master Pegawai Protokol')
            ->assertSee('Dewi Kurnia, S.AP');
    }

    public function test_livewire_can_search_and_filter_protokol(): void
    {
        $this->withoutVite();

        Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);
        Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '082222', 'status' => ProtokolStatus::ACTIVE]);
        Protokol::create(['name' => 'Fajar Nonaktif', 'phone_wa' => '083333', 'status' => ProtokolStatus::INACTIVE]);

        $this->actingAs($this->admin);

        Livewire::test(DaftarProtokol::class)
            ->set('search', 'Dewi')
            ->assertSee('Dewi Kurnia')
            ->assertDontSee('Andi Prasetyo')
            ->set('search', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('Fajar Nonaktif')
            ->assertDontSee('Dewi Kurnia');
    }

    public function test_livewire_can_toggle_protokol_status(): void
    {
        $this->withoutVite();

        $proto = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);

        $this->actingAs($this->admin);

        Livewire::test(DaftarProtokol::class)
            ->call('toggleStatus', $proto->id);

        $proto->refresh();
        $this->assertEquals(ProtokolStatus::INACTIVE, $proto->status);
    }

    public function test_livewire_can_create_new_protokol(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin);

        Livewire::test(DaftarProtokol::class)
            ->call('openCreateModal')
            ->set('name', 'Rina Wahyuni')
            ->set('phone_wa', '087812345678')
            ->set('nip', '199501012020012001')
            ->set('status', 'active')
            ->call('saveProtokol')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('protokols', [
            'name' => 'Rina Wahyuni',
            'phone_wa' => '087812345678',
            'status' => 'active',
        ]);
    }

    public function test_livewire_can_open_and_view_stats_modal(): void
    {
        $this->withoutVite();

        $proto = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);

        $this->actingAs($this->admin);

        Livewire::test(DaftarProtokol::class)
            ->call('openStatsModal', $proto->id)
            ->assertSet('showStatsModal', true)
            ->assertSee('Statistik Penugasan Pegawai');
    }

    public function test_livewire_can_confirm_and_delete_protokol(): void
    {
        $this->withoutVite();

        $proto = Protokol::create([
            'name' => 'Bambang Sudirman',
            'phone_wa' => '081299998888',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->actingAs($this->admin);

        // 1. Open confirm modal
        Livewire::test(DaftarProtokol::class)
            ->call('confirmDelete', $proto->id)
            ->assertSet('showDeleteModal', true)
            ->assertSet('deletingProtokolName', 'Bambang Sudirman')
            ->assertSet('deletingProtokolTaskCount', 0)
            ->call('deleteProtokol')
            ->assertSet('showDeleteModal', false);

        // 2. Assert soft deleted in database
        $this->assertSoftDeleted('protokols', [
            'id' => $proto->id,
            'name' => 'Bambang Sudirman',
        ]);
    }
}
