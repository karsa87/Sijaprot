<?php

namespace Tests\Feature;

use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Models\Protokol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterProtokolControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Budi Santoso',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $this->operator = User::create([
            'name' => 'Siti Aminah',
            'email' => 'operator@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::OPERATOR,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/master/protokol');
        $response->assertRedirect('/login');
    }

    public function test_operator_forbidden_from_master_protokol(): void
    {
        $this->actingAs($this->operator)
            ->get('/master/protokol')
            ->assertStatus(403);
    }

    public function test_admin_can_view_daftar_protokol_inertia(): void
    {
        $proto = Protokol::create([
            'name' => 'Dewi Kurnia, S.AP',
            'phone_wa' => '081234567890',
            'nip' => '199203152018012001',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get('/master/protokol')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/DaftarProtokol')
                ->has('protokols.data', 1)
                ->where('protokols.data.0.name', 'Dewi Kurnia, S.AP')
            );
    }

    public function test_search_and_filter_protokol(): void
    {
        Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '0811111111', 'status' => ProtokolStatus::ACTIVE]);
        Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '0822222222', 'status' => ProtokolStatus::ACTIVE]);
        Protokol::create(['name' => 'Fajar Nonaktif', 'phone_wa' => '0833333333', 'status' => ProtokolStatus::INACTIVE]);

        $this->actingAs($this->admin)
            ->get('/master/protokol?search=Dewi')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/DaftarProtokol')
                ->has('protokols.data', 1)
                ->where('protokols.data.0.name', 'Dewi Kurnia')
            );

        $this->actingAs($this->admin)
            ->get('/master/protokol?status=inactive')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/DaftarProtokol')
                ->has('protokols.data', 1)
                ->where('protokols.data.0.name', 'Fajar Nonaktif')
            );
    }

    public function test_can_create_new_protokol_with_valid_whatsapp(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/master/protokol', [
                'name' => 'Rina Wahyuni',
                'phone_wa' => '087812345678',
                'nip' => '199501012020012001',
                'status' => 'active',
                'notes' => 'Protokol Bahasa Inggris',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('protokols', [
            'name' => 'Rina Wahyuni',
            'phone_wa' => '087812345678',
        ]);
    }

    public function test_cannot_create_protokol_with_invalid_whatsapp(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/master/protokol', [
                'name' => 'Rina Wahyuni',
                'phone_wa' => '021-5551234', // Landline bukan ponsel Indo
                'status' => 'active',
            ]);

        $response->assertSessionHasErrors(['phone_wa']);
        $this->assertDatabaseMissing('protokols', ['name' => 'Rina Wahyuni']);
    }

    public function test_can_toggle_protokol_status(): void
    {
        $proto = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '0811111111',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->patch("/master/protokol/{$proto->id}/toggle-status")
            ->assertRedirect();

        $proto->refresh();
        $this->assertEquals(ProtokolStatus::INACTIVE, $proto->status);
    }

    public function test_can_fetch_protokol_stats_json(): void
    {
        $proto = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '0811111111',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/master/protokol/{$proto->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'protokol' => ['id', 'name', 'phone_wa'],
                'history',
            ]);
    }

    public function test_can_delete_protokol(): void
    {
        $proto = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '0811111111',
            'status' => ProtokolStatus::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->delete("/master/protokol/{$proto->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('protokols', ['id' => $proto->id]);
    }
}
