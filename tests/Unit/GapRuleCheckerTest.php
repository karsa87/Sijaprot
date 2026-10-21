<?php

namespace Tests\Unit;

use App\Enums\LocationType;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use App\Services\Scheduling\GapRuleChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GapRuleCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected GapRuleChecker $checker;

    protected Protokol $dewi;

    protected JenisKegiatan $jenisDalamKota;

    protected JenisKegiatan $jenisLuarKota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new GapRuleChecker;

        $this->dewi = Protokol::create([
            'name' => 'Dewi Kurnia',
            'phone_wa' => '081234567890',
            'status' => 'active',
        ]);

        $this->jenisDalamKota = JenisKegiatan::create([
            'name' => 'Rapat Pimpinan',
            'code' => 'RAPAT_TEST',
            'payment_type' => 'paid',
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->jenisLuarKota = JenisKegiatan::create([
            'name' => 'Kunker Luar Kota',
            'code' => 'KUNKER_TEST',
            'payment_type' => 'paid',
            'location_type' => LocationType::LUAR_KOTA->value,
        ]);
    }

    /**
     * H1: Dalam Kota jeda minimal 24 jam dari jam selesai kegiatan.
     */
    public function test_h1_dalam_kota_requires_24_hours_gap(): void
    {
        // Tugas 1: Selesai Senin 20 Okt jam 12:00
        $kegiatan1 = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Senin Siang',
            'start_datetime' => '2026-10-19 09:00:00',
            'end_datetime' => '2026-10-19 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatan1->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-19 09:00:00',
        ]);

        // Kasus A: Tugas baru Selasa 20 Okt jam 10:00 (Hanya 22 jam setelah jam 12:00) -> DITOLAK
        $kegiatanBaruGagal = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Selasa Pagi',
            'start_datetime' => '2026-10-20 10:00:00',
            'end_datetime' => '2026-10-20 13:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertFalse($this->checker->passesAllHardRules($this->dewi, $kegiatanBaruGagal));

        // Kasus B: Tugas baru Selasa 20 Okt jam 13:00 (25 jam setelah jam 12:00) -> DITERIMA
        $kegiatanBaruLolos = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Selasa Siang',
            'start_datetime' => '2026-10-20 13:00:00',
            'end_datetime' => '2026-10-20 16:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertTrue($this->checker->passesAllHardRules($this->dewi, $kegiatanBaruLolos));
    }

    /**
     * H1: Luar Kota mengunci status hingga kepulangan + H+1 hari kalender penuh diblokir.
     */
    public function test_h1_luar_kota_blocks_full_next_calendar_day(): void
    {
        // Tugas Luar Kota: Senin 19 Okt s.d. Rabu 21 Okt 17:00
        $kegiatanLuarKota = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisLuarKota->id,
            'title' => 'Kunker Surabaya',
            'start_datetime' => '2026-10-19 08:00:00',
            'end_datetime' => '2026-10-21 17:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::LUAR_KOTA->value,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatanLuarKota->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-19 08:00:00',
        ]);

        // Kamis 22 Okt (H+1 hari kalender dari Rabu) -> DITOLAK
        $kegiatanKamis = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat Kamis',
            'start_datetime' => '2026-10-22 19:00:00',
            'end_datetime' => '2026-10-22 21:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);
        $this->assertFalse($this->checker->passesAllHardRules($this->dewi, $kegiatanKamis));

        // Jumat 23 Okt (H+2 hari kalender dari Rabu) -> DITERIMA
        $kegiatanJumat = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Rapat Jumat',
            'start_datetime' => '2026-10-23 08:00:00',
            'end_datetime' => '2026-10-23 11:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);
        $this->assertTrue($this->checker->passesAllHardRules($this->dewi, $kegiatanJumat));
    }

    /**
     * H2: Tidak boleh ada overlap waktu langsung.
     */
    public function test_h2_direct_overlap_is_rejected(): void
    {
        $kegiatan1 = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas A',
            'start_datetime' => '2026-10-20 09:00:00',
            'end_datetime' => '2026-10-20 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
            'kegiatan_id' => $kegiatan1->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-20 09:00:00',
        ]);

        // Kegiatan beririsan (11:00 - 14:00)
        $kegiatanOverlap = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Overlap',
            'start_datetime' => '2026-10-20 11:00:00',
            'end_datetime' => '2026-10-20 14:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertTrue($this->checker->hasDirectOverlap($this->dewi, $kegiatanOverlap));
        $this->assertFalse($this->checker->passesAllHardRules($this->dewi, $kegiatanOverlap));
    }

    /**
     * H3: Protokol yang bertugas Sabtu/Minggu diblokir di Sabtu & Minggu berikutnya.
     */
    public function test_h3_weekend_guard_blocks_next_full_weekend(): void
    {
        // Dewi bertugas di hari Sabtu, 3 Oktober 2026
        $tugasSabtuLalu = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Upacara Sabtu 3 Okt',
            'start_datetime' => '2026-10-03 08:00:00',
            'end_datetime' => '2026-10-03 12:00:00',
            'jumlah_protokol' => 1,
            'is_weekend' => true,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);

        Penugasan::create([
            'kegiatan_id' => $tugasSabtuLalu->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-03 08:00:00',
        ]);

        // Cek penugasan Sabtu berikutnya (10 Oktober 2026) -> DITOLAK
        $tugasSabtuDepan = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Workshop Sabtu 10 Okt',
            'start_datetime' => '2026-10-10 08:00:00',
            'end_datetime' => '2026-10-10 12:00:00',
            'is_weekend' => true,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertFalse($this->checker->passesWeekendProtection($this->dewi, $tugasSabtuDepan));
        $this->assertFalse($this->checker->passesAllHardRules($this->dewi, $tugasSabtuDepan));

        // Cek penugasan Minggu berikutnya (11 Oktober 2026) -> DITOLAK
        $tugasMingguDepan = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Workshop Minggu 11 Okt',
            'start_datetime' => '2026-10-11 08:00:00',
            'end_datetime' => '2026-10-11 12:00:00',
            'is_weekend' => true,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertFalse($this->checker->passesWeekendProtection($this->dewi, $tugasMingguDepan));

        // Cek penugasan Sabtu ke-2 setelahnya (17 Oktober 2026) -> DITERIMA
        $tugasWeekend2MingguKemudian = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Acara Sabtu 17 Okt',
            'start_datetime' => '2026-10-17 08:00:00',
            'end_datetime' => '2026-10-17 12:00:00',
            'is_weekend' => true,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertTrue($this->checker->passesWeekendProtection($this->dewi, $tugasWeekend2MingguKemudian));
    }

    /**
     * BUG-02: Gap Checker wajib mengurutkan berdasarkan end_datetime kegiatan, bukan assigned_at.
     */
    public function test_h1_orders_by_end_datetime_not_assigned_at_when_tasks_inserted_out_of_chronological_order(): void
    {
        // Tugas Kronologis Lebih Baru (20 Okt jam 10:00 - 12:00), tapi di-assign lebih awal (18 Okt)
        $tugasKronologisTerbaru = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas 20 Okt',
            'start_datetime' => '2026-10-20 10:00:00',
            'end_datetime' => '2026-10-20 12:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $tugasKronologisTerbaru->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-18 08:00:00', // Waktu assign lebih dulu
        ]);

        // Tugas Kronologis Lebih Lama (18 Okt jam 08:00 - 10:00), tapi di-assign belakangan (21 Okt)
        $tugasKronologisLama = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas 18 Okt',
            'start_datetime' => '2026-10-18 08:00:00',
            'end_datetime' => '2026-10-18 10:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $tugasKronologisLama->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-21 16:00:00', // Waktu assign paling akhir
        ]);

        // Tugas baru: 21 Okt jam 08:00 (Hanya 20 jam setelah Tugas 20 Okt 12:00)
        // Jika bug ada (order by assigned_at DESC), sistem akan membandingkan dengan Tugas 18 Okt (jeda > 48 jam) dan meloloskan.
        // Dengan perbaikan, sistem wajib membandingkan dengan Tugas 20 Okt (jeda 20 jam < 24 jam) dan MENOLAK.
        $tugasBaru = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas 21 Okt Pagi',
            'start_datetime' => '2026-10-21 08:00:00',
            'end_datetime' => '2026-10-21 11:00:00',
            'jumlah_protokol' => 1,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        $this->assertFalse(
            $this->checker->passesHybridGap($this->dewi, $tugasBaru),
            'Tugas harus ditolak karena jeda terhadap kegiatan terakhir (20 Okt 12:00) kurang dari 24 jam.'
        );
    }

    /**
     * BUG-06: Formula Weekend Guard (H3) wajib memeriksa akhir pekan persis sebelumnya pada libur tengah pekan.
     */
    public function test_h3_weekend_guard_correctly_identifies_preceding_weekend_on_midweek_holiday(): void
    {
        // Dewi bertugas pada Sabtu, 24 Oktober 2026
        $tugasSabtu = Kegiatan::create([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Tugas Sabtu 24 Okt',
            'start_datetime' => '2026-10-24 08:00:00',
            'end_datetime' => '2026-10-24 12:00:00',
            'jumlah_protokol' => 1,
            'is_weekend' => true,
            'location_type' => LocationType::DALAM_KOTA->value,
            'status' => 'scheduled',
        ]);
        Penugasan::create([
            'kegiatan_id' => $tugasSabtu->id,
            'protokol_id' => $this->dewi->id,
            'assigned_at' => '2026-10-24 08:00:00',
        ]);

        // Tugas baru di Hari Libur Nasional pada Rabu, 28 Oktober 2026
        $tugasLiburRabu = new Kegiatan([
            'jenis_kegiatan_id' => $this->jenisDalamKota->id,
            'title' => 'Upacara Hari Sumpah Pemuda (Libur Nasional)',
            'start_datetime' => '2026-10-28 08:00:00',
            'end_datetime' => '2026-10-28 11:00:00',
            'jumlah_protokol' => 1,
            'is_holiday' => true,
            'location_type' => LocationType::DALAM_KOTA->value,
        ]);

        // Karena Dewi sudah bertugas di akhir pekan sebelumnya (24-25 Okt), Dewi wajib DITOLAK
        $this->assertFalse(
            $this->checker->passesWeekendProtection($this->dewi, $tugasLiburRabu),
            'Petugas yang berdinas di akhir pekan sebelumnya harus diblokir pada hari libur nasional tengah pekan.'
        );
    }
}
