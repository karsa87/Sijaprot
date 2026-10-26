<?php

namespace Tests\Feature;

use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Exports\RekapDistribusiExport;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanExportTest extends TestCase
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

        $protokol = Protokol::create(['name' => 'Dewi Kurnia', 'phone_wa' => '081111', 'status' => ProtokolStatus::ACTIVE]);

        $jenis = JenisKegiatan::create([
            'code' => 'KUNKER_LK',
            'name' => 'Kunjungan Kerja Luar Kota',
            'payment_type' => PaymentType::PAID,
            'payment_day_type' => PaymentDayType::WORKDAY,
            'is_kunker' => true,
        ]);

        $kegiatan = Kegiatan::create([
            'title' => 'Kunker DPR RI Bali',
            'jenis_kegiatan_id' => $jenis->id,
            'start_datetime' => Carbon::create(2026, 10, 15, 9, 0, 0),
            'end_datetime' => Carbon::create(2026, 10, 15, 12, 0, 0),
            'location' => 'Denpasar, Bali',
            'is_kunker' => true,
            'status' => 'completed',
        ]);

        $kegiatan->protokols()->attach($protokol->id, ['is_system_assigned' => true]);
    }

    public function test_can_export_excel(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/laporan/export/excel?month=10&year=2026');

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_can_export_pdf(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/laporan/export/pdf?month=10&year=2026');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', $response->headers->get('content-disposition'));
    }

    public function test_export_excel_structure_and_headings_match_prd_v2(): void
    {
        $export = new RekapDistribusiExport(10, 2026);
        $headings = $export->headings();

        $tableHeaders = $headings[3];
        $this->assertContains('Kunker (Total & Rincian Agenda)', $tableHeaders);
        $this->assertContains('WFA (Jum\'at)', $tableHeaders);
        $this->assertContains('Status Keseimbangan', $tableHeaders);
        $this->assertNotContains('NIP', $tableHeaders);

        $collection = $export->collection();
        $this->assertNotEmpty($collection);

        $mapped = $export->map($collection->first());
        $this->assertEquals('Dewi Kurnia', $mapped[1]);
        $this->assertEquals('081111', $mapped[2]);
        // Column F (index 5) should contain kunker summary with bullet point and date
        $this->assertStringContainsString('1x', $mapped[5]);
        $this->assertStringContainsString('15/10: Kunker DPR RI Bali', $mapped[5]);
        // Column K (index 10) should contain Status Keseimbangan
        $this->assertNotEmpty($mapped[10]);
    }
}
