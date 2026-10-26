<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator Protokol',
            'email' => 'operator@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::OPERATOR,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard_inertia(): void
    {
        $jenis = JenisKegiatan::create([
            'name' => 'Upacara Kenegaraan',
            'code' => 'UPACARA',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => 'dalam_kota',
        ]);

        $kegiatan = Kegiatan::create([
            'title' => 'Upacara Hari Pendidikan',
            'jenis_kegiatan_id' => $jenis->id,
            'start_datetime' => Carbon::now()->startOfMonth()->addDays(2)->setHour(8),
            'end_datetime' => Carbon::now()->startOfMonth()->addDays(2)->setHour(11),
            'location_type' => 'dalam_kota',
            'required_protocols' => 2,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/KalenderMonitoring')
                ->has('kegiatans', 1)
                ->where('kegiatans.0.title', 'Upacara Hari Pendidikan')
                ->has('stats.totalKegiatan')
                ->has('stats.indeksKeseimbangan')
            );
    }

    public function test_can_navigate_month_in_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get('/dashboard?month=12&year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/KalenderMonitoring')
                ->where('month', 12)
                ->where('year', 2026)
            );
    }

    public function test_can_delete_kegiatan_from_dashboard(): void
    {
        $jenis = JenisKegiatan::create([
            'name' => 'Upacara Kenegaraan',
            'code' => 'UPACARA',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => 'dalam_kota',
        ]);

        $kegiatan = Kegiatan::create([
            'title' => 'Kegiatan Batal',
            'jenis_kegiatan_id' => $jenis->id,
            'start_datetime' => Carbon::now()->addDays(1),
            'end_datetime' => Carbon::now()->addDays(1)->addHours(2),
            'location_type' => 'dalam_kota',
            'required_protocols' => 1,
            'status' => 'scheduled',
        ]);

        $proto = Protokol::create(['name' => 'Budi', 'phone_wa' => '081234567890']);
        $kegiatan->protokols()->attach($proto->id, ['is_system_assigned' => true]);

        $response = $this->actingAs($this->user)
            ->delete("/kegiatan/{$kegiatan->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('kegiatan', ['id' => $kegiatan->id]);
    }
}
