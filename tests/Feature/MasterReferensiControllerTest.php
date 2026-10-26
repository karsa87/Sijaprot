<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterReferensiControllerTest extends TestCase
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
        $this->get('/master/referensi')->assertRedirect('/login');
    }

    public function test_operator_forbidden_from_master_referensi(): void
    {
        $this->actingAs($this->operator)
            ->get('/master/referensi')
            ->assertStatus(403);
    }

    public function test_admin_can_view_master_referensi_inertia(): void
    {
        JenisKegiatan::create([
            'name' => 'Upacara Kenegaraan',
            'code' => 'UPACARA',
            'payment_type' => 'paid',
            'payment_day_type' => 'workday',
            'location_type' => 'dalam_kota',
            'is_kunker' => false,
            'estimated_fee' => 150000,
        ]);

        $this->actingAs($this->admin)
            ->get('/master/referensi')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Master/MasterReferensi')
                ->has('jenisKegiatans')
                ->has('hariLiburs')
            );
    }

    public function test_can_create_and_update_jenis_kegiatan(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/master/referensi/jenis', [
                'name' => 'Sidang Pleno Menteri',
                'code' => 'SIDANG_PLENO',
                'payment_type' => 'conditional',
                'payment_day_type' => 'workday',
                'location_type' => 'dalam_kota',
                'is_kunker' => true,
                'estimated_fee' => 200000,
                'description' => 'SOP Protokol Sidang Pleno',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('jenis_kegiatan', [
            'code' => 'SIDANG_PLENO',
            'payment_type' => 'conditional',
            'is_kunker' => true,
        ]);

        $jenis = JenisKegiatan::where('code', 'SIDANG_PLENO')->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/master/referensi/jenis/{$jenis->id}", [
                'name' => 'Sidang Pleno Menteri Updated',
                'code' => 'SIDANG_PLENO',
                'payment_type' => 'paid',
                'payment_day_type' => 'offday',
                'location_type' => 'luar_kota',
                'is_kunker' => false,
                'estimated_fee' => 350000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('jenis_kegiatan', [
            'id' => $jenis->id,
            'name' => 'Sidang Pleno Menteri Updated',
            'location_type' => 'luar_kota',
            'payment_type' => 'paid',
            'is_kunker' => false,
        ]);

        // Test update to WFA payment_day_type
        $this->actingAs($this->admin)
            ->put("/master/referensi/jenis/{$jenis->id}", [
                'name' => 'Sidang Pleno WFA',
                'code' => 'SIDANG_PLENO',
                'payment_type' => 'paid',
                'payment_day_type' => 'wfa',
                'location_type' => 'dalam_kota',
                'is_kunker' => false,
                'estimated_fee' => 250000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('jenis_kegiatan', [
            'id' => $jenis->id,
            'payment_day_type' => 'wfa',
        ]);
        $this->assertTrue($jenis->fresh()->isWfa());
    }

    public function test_safe_deletion_hard_deletes_when_no_activities(): void
    {
        $jenis = JenisKegiatan::create([
            'name' => 'Jenis Kosong',
            'code' => 'JENIS_KOSONG',
            'payment_type' => 'unpaid',
            'location_type' => 'dalam_kota',
            'is_kunker' => false,
            'estimated_fee' => 0,
        ]);

        $this->actingAs($this->admin)
            ->delete("/master/referensi/jenis/{$jenis->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('jenis_kegiatan', ['id' => $jenis->id]);
    }

    public function test_safe_deletion_soft_deletes_when_has_activities(): void
    {
        $jenis = JenisKegiatan::create([
            'name' => 'Kunker Pimpinan',
            'code' => 'KUNKER_PIMPINAN',
            'payment_type' => PaymentType::CONDITIONAL,
            'location_type' => LocationType::LUAR_KOTA,
            'is_kunker' => true,
            'estimated_fee' => 500000,
        ]);

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $jenis->id,
            'title' => 'Kunjungan Kerja DPR RI',
            'start_datetime' => Carbon::tomorrow()->setHour(9),
            'end_datetime' => Carbon::tomorrow()->setHour(12),
            'jumlah_protokol' => 2,
            'is_holiday' => false,
            'is_weekend' => false,
            'is_kunker' => true,
            'location_type' => LocationType::LUAR_KOTA,
            'status' => KegiatanStatus::SCHEDULED,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/master/referensi/jenis/{$jenis->id}")
            ->assertRedirect();

        // Must be soft deleted (not permanently wiped) to preserve 100% historical integrity
        $this->assertSoftDeleted('jenis_kegiatan', ['id' => $jenis->id]);
        $this->assertDatabaseHas('kegiatan', ['id' => $kegiatan->id, 'jenis_kegiatan_id' => $jenis->id]);
    }

    public function test_cannot_input_estimated_fee_exceeding_threshold(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/master/referensi/jenis', [
                'name' => 'Acara Super Mewah',
                'code' => 'SUPER_MEWAH',
                'payment_type' => 'paid',
                'location_type' => 'dalam_kota',
                'estimated_fee' => 100000000000, // BUG-07: Melampaui limit max:999999999
            ]);

        $response->assertSessionHasErrors(['estimated_fee']);
        $this->assertDatabaseMissing('jenis_kegiatan', ['code' => 'SUPER_MEWAH']);
    }

    public function test_can_create_and_delete_hari_libur(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/master/referensi/libur', [
                'tanggal' => '2026-08-17',
                'name' => 'HUT Kemerdekaan RI ke-81',
                'type' => 'nasional',
            ]);

        $response->assertRedirect();

        $libur = HariLibur::whereDate('tanggal', '2026-08-17')->first();
        $this->assertNotNull($libur);
        $this->assertEquals(2026, $libur->tahun);
        $this->assertEquals('HUT Kemerdekaan RI ke-81', $libur->name);

        $this->actingAs($this->admin)
            ->delete("/master/referensi/libur/{$libur->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('hari_libur', ['id' => $libur->id]);
    }
}
