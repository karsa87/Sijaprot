<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessAutoSchedulingJob;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessAutoSchedulingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched_and_executed(): void
    {
        $admin = User::create([
            'name' => 'Budi Santoso',
            'email' => 'admin@kemdikbud.go.id',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN_PROTOKOL,
        ]);

        $proto1 = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);
        $proto2 = Protokol::create(['name' => 'Andi Prasetyo', 'phone_wa' => '082222', 'status' => ProtokolStatus::ACTIVE]);

        $jenis = JenisKegiatan::create([
            'name' => 'Kunjungan Tamu VIP',
            'code' => 'VIP',
            'payment_type' => 'paid',
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $jenis->id,
            'title' => 'Penyambutan Dubes',
            'start_datetime' => '2026-10-20 08:30:00',
            'end_datetime' => '2026-10-20 12:00:00',
            'jumlah_protokol' => 2,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::DRAFT->value,
            'created_by' => $admin->id,
        ]);

        // Dispatch Job synchronously to verify full execution
        ProcessAutoSchedulingJob::dispatchSync($kegiatan, $admin->id);

        $kegiatan->refresh();
        $this->assertEquals(KegiatanStatus::SCHEDULED, $kegiatan->status);
        $this->assertDatabaseCount('penugasan', 2);
    }
}
