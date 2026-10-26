<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Protokol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanDistribusiControllerTest extends TestCase
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
        $this->get('/laporan/distribusi')->assertRedirect('/login');
    }

    public function test_operator_is_forbidden_from_laporan_distribusi(): void
    {
        $this->actingAs($this->operator)
            ->get('/laporan/distribusi')
            ->assertStatus(403);
    }

    public function test_admin_can_view_laporan_distribusi_inertia(): void
    {
        $protokol = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '081234567890',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->get('/laporan/distribusi')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan/RekapDistribusi')
                ->has('protokols', 1)
                ->where('protokols.0.name', 'Dewi Kurnia')
                ->has('protokols.0.total_tugas_bulan_ini')
                ->has('protokols.0.tugas_kunker')
                ->has('protokols.0.tugas_wfa')
                ->has('protokols.0.tugas_dibayar_total')
                ->has('protokols.0.tugas_paid')
                ->has('protokols.0.tugas_conditional')
                ->has('protokols.0.status_keseimbangan')
                ->has('monthName')
            );
    }

    public function test_can_filter_by_month_and_year(): void
    {
        $this->actingAs($this->admin)
            ->get('/laporan/distribusi?month=8&year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Laporan/RekapDistribusi')
                ->where('selectedMonth', 8)
                ->where('selectedYear', 2026)
            );
    }
}
