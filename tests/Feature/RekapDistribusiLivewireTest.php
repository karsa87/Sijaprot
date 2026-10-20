<?php

namespace Tests\Feature;

use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Livewire\Laporan\RekapDistribusi;
use App\Models\Protokol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RekapDistribusiLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
    }

    public function test_can_view_laporan_distribusi_page(): void
    {
        Protokol::create(['name' => 'Dewi Kurnia, S.AP', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);

        $this->actingAs($this->admin)
            ->get('/laporan/distribusi')
            ->assertStatus(200)
            ->assertSee('Rekapitulasi Keseimbangan Tugas')
            ->assertSee('Dewi Kurnia, S.AP')
            ->assertSee('Export Excel')
            ->assertSee('Export PDF');
    }

    public function test_livewire_can_filter_by_month_and_year(): void
    {
        Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);

        $this->actingAs($this->admin);

        Livewire::test(RekapDistribusi::class)
            ->set('selectedMonth', 10)
            ->set('selectedYear', 2026)
            ->assertSee('Dewi Kurnia')
            ->assertStatus(200);
    }
}
