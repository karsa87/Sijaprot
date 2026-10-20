<?php

namespace Database\Seeders;

use App\Enums\LiburType;
use App\Enums\LocationType;
use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use App\Enums\ProtokolStatus;
use App\Enums\UserRole;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use App\Models\Protokol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Default Admin User
        User::firstOrCreate(
            ['email' => 'admin@kemdikbud.go.id'],
            [
                'name' => 'Dewi Prawitasari',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN_PROTOKOL,
            ]
        );

        // 2. Default Master Jenis Kegiatan
        $templates = [
            [
                'name' => 'Kunjungan Tamu VIP Negara',
                'code' => 'VIP_DALAM',
                'payment_type' => PaymentType::PAID,
                'payment_day_type' => PaymentDayType::WORKDAY,
                'location_type' => LocationType::DALAM_KOTA,
                'estimated_fee' => 350000.00,
                'description' => 'Penyambutan Dubes / Tamu Menteri di hari kerja',
            ],
            [
                'name' => 'Upacara & Peringatan Hari Besar',
                'code' => 'UPACARA_WEEKEND',
                'payment_type' => PaymentType::PAID,
                'payment_day_type' => PaymentDayType::OFFDAY,
                'location_type' => LocationType::DALAM_KOTA,
                'estimated_fee' => 500000.00,
                'description' => 'Upacara bendera / apel di akhir pekan atau hari libur nasional',
            ],
            [
                'name' => 'Pendampingan Dinas Luar Kota Menteri',
                'code' => 'DINAS_LUARKOTA',
                'payment_type' => PaymentType::PAID,
                'payment_day_type' => PaymentDayType::WORKDAY,
                'location_type' => LocationType::LUAR_KOTA,
                'estimated_fee' => 750000.00,
                'description' => 'Kunker pimpinan ke daerah, mengaktifkan aturan gap H+1',
            ],
            [
                'name' => 'Rapat Koordinasi Pimpinan Internal',
                'code' => 'RAPAT_INTERNAL',
                'payment_type' => PaymentType::UNPAID,
                'payment_day_type' => null,
                'location_type' => LocationType::DALAM_KOTA,
                'estimated_fee' => 0.00,
                'description' => 'Pelayanan protokoler ruang sidang & rapat dinas rutin internal',
            ],
        ];

        foreach ($templates as $t) {
            JenisKegiatan::firstOrCreate(['code' => $t['code']], $t);
        }

        // 3. Master Hari Libur Nasional 2026
        $holidays = [
            ['tanggal' => '2026-08-17', 'tahun' => 2026, 'name' => 'Hari Kemerdekaan RI ke-81', 'type' => LiburType::NASIONAL],
            ['tanggal' => '2026-10-28', 'tahun' => 2026, 'name' => 'Hari Sumpah Pemuda (Upacara Khusus)', 'type' => LiburType::HARI_BESAR],
            ['tanggal' => '2026-12-25', 'tahun' => 2026, 'name' => 'Hari Raya Natal', 'type' => LiburType::NASIONAL],
        ];

        foreach ($holidays as $h) {
            HariLibur::firstOrCreate(['tanggal' => $h['tanggal']], $h);
        }

        // 4. Sample Protokol Team (10 Persons as in Prototype)
        $protocols = [
            ['name' => 'Dewi Kurnia, S.AP', 'phone_wa' => '0812-3456-7890', 'nip' => '199203152018012001', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Andi Prasetyo', 'phone_wa' => '0812-9876-5432', 'nip' => '199008122017011002', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Fajar Hidayat', 'phone_wa' => '0856-1234-5678', 'nip' => '199405202019011003', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Rina Wahyuni', 'phone_wa' => '0878-1234-5678', 'nip' => '199307182018012004', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Bayu Nugroho', 'phone_wa' => '0813-8888-9999', 'nip' => '199101052016011005', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Dina Kartika', 'phone_wa' => '0811-3333-7777', 'nip' => '199511252020012006', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Siti Rahmawati', 'phone_wa' => '0821-4444-2222', 'nip' => '199604102021012007', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Eko Wicaksono', 'phone_wa' => '0819-5555-1111', 'nip' => '198912302015011008', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Hendra Wijaya', 'phone_wa' => '0852-9999-1234', 'nip' => '199209142018011009', 'status' => ProtokolStatus::ACTIVE],
            ['name' => 'Indah Permata', 'phone_wa' => '0812-7777-6666', 'nip' => '199702282022012010', 'status' => ProtokolStatus::INACTIVE],
        ];

        foreach ($protocols as $p) {
            Protokol::firstOrCreate(['phone_wa' => $p['phone_wa']], $p);
        }
    }
}
