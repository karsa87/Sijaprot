<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KegiatanControllerTest extends TestCase
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

        $this->admin = User::create([
            'name' => 'Budi Santoso',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $this->dewi = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '0811111111', 'status' => ProtokolStatus::ACTIVE]);
        $this->andi = Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '0822222222', 'status' => ProtokolStatus::ACTIVE]);

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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/kegiatan/baru')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_create_form_inertia(): void
    {
        $this->actingAs($this->admin)
            ->get('/kegiatan/baru')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kegiatan/FormKegiatan')
                ->where('isEditing', false)
                ->has('jenisList')
                ->has('activeProtokols')
            );
    }

    public function test_run_simulation_endpoint_produces_recommendations(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/kegiatan/simulasi', [
                'title' => 'Simulasi Rapat Koordinasi',
                'jenis_kegiatan_id' => $this->jenisDalamKota->id,
                'jumlah_protokol' => 2,
                'start_date' => Carbon::now()->addDays(2)->toDateString(),
                'start_time' => '09:00',
                'end_date' => Carbon::now()->addDays(2)->toDateString(),
                'end_time' => '12:00',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recommendations',
                'relaxation_level',
                'total_eligible',
            ]);

        $this->assertCount(2, $response->json('recommendations'));
    }

    public function test_simulation_rejects_negative_or_inverted_duration(): void
    {
        // BUG-03: Waktu selesai lebih awal dari waktu mulai di hari yang sama
        $response = $this->actingAs($this->admin)
            ->postJson('/kegiatan/simulasi', [
                'title' => 'Acara Jam Terbalik',
                'jenis_kegiatan_id' => $this->jenisDalamKota->id,
                'jumlah_protokol' => 1,
                'start_date' => Carbon::now()->addDays(2)->toDateString(),
                'start_time' => '14:00',
                'end_date' => Carbon::now()->addDays(2)->toDateString(),
                'end_time' => '10:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_can_store_new_kegiatan_with_atomic_lock(): void
    {
        $startDate = Carbon::now()->addDays(3)->toDateString();

        $response = $this->actingAs($this->admin)
            ->post('/kegiatan', [
                'title' => 'Sidang Paripurna Baru',
                'jenis_kegiatan_id' => $this->jenisDalamKota->id,
                'jumlah_protokol' => 1,
                'start_date' => $startDate,
                'start_time' => '08:00',
                'end_date' => $startDate,
                'end_time' => '11:00',
                'recommendations' => [
                    ['protokol' => ['id' => $this->dewi->id]],
                ],
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('kegiatan', ['title' => 'Sidang Paripurna Baru']);

        $kegiatan = Kegiatan::where('title', 'Sidang Paripurna Baru')->firstOrFail();
        $this->assertDatabaseHas('penugasan', [
            'kegiatan_id' => $kegiatan->id,
            'protokol_id' => $this->dewi->id,
        ]);
    }

    public function test_can_view_edit_form_inertia(): void
    {
        $kegiatan = Kegiatan::create([
            'title' => 'Kegiatan Edit Test',
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'start_datetime' => Carbon::now()->addDays(4)->setHour(9),
            'end_datetime' => Carbon::now()->addDays(4)->setHour(12),
            'location_type' => 'dalam_kota',
            'jumlah_protokol' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->get("/kegiatan/{$kegiatan->id}/edit")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kegiatan/FormKegiatan')
                ->where('isEditing', true)
                ->where('kegiatan.title', 'Kegiatan Edit Test')
            );
    }

    public function test_can_update_existing_kegiatan(): void
    {
        $kegiatan = Kegiatan::create([
            'title' => 'Kegiatan Sebelum Update',
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'start_datetime' => Carbon::now()->addDays(5)->setHour(9),
            'end_datetime' => Carbon::now()->addDays(5)->setHour(12),
            'location_type' => 'dalam_kota',
            'jumlah_protokol' => 1,
            'status' => 'draft',
        ]);

        $startDate = Carbon::now()->addDays(5)->toDateString();

        $response = $this->actingAs($this->admin)
            ->put("/kegiatan/{$kegiatan->id}", [
                'title' => 'Kegiatan Setelah Update Sukses',
                'jenis_kegiatan_id' => $this->jenisDalamKota->id,
                'jumlah_protokol' => 1,
                'start_date' => $startDate,
                'start_time' => '10:00',
                'end_date' => $startDate,
                'end_time' => '13:00',
                'recommendations' => [
                    ['protokol' => ['id' => $this->andi->id]],
                ],
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('kegiatan', ['title' => 'Kegiatan Setelah Update Sukses']);
    }

    public function test_can_soft_delete_kegiatan(): void
    {
        $kegiatan = Kegiatan::create([
            'title' => 'Kegiatan Mau Dihapus',
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'start_datetime' => Carbon::now()->addDays(6)->setHour(9),
            'end_datetime' => Carbon::now()->addDays(6)->setHour(12),
            'location_type' => 'dalam_kota',
            'jumlah_protokol' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->delete("/kegiatan/{$kegiatan->id}")
            ->assertRedirect('/dashboard');

        $this->assertSoftDeleted('kegiatan', ['id' => $kegiatan->id]);
    }
}
