<?php

namespace App\Exports;

use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapDistribusiExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    protected int $month;
    protected int $year;
    protected int $rowNumber = 0;

    public function __construct(int $month, int $year)
    {
        $this->month = $month;
        $this->year = $year;
    }

    public function collection(): Collection
    {
        return Protokol::active()
            ->withMonthlyMetrics($this->month, $this->year)
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        $monthName = Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');

        return [
            ['KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH RI'],
            ["REKAPITULASI KESEIMBANGAN DISTRIBUSI TUGAS PROTOKOL - {$monthName}"],
            [],
            [
                'No',
                'NIP',
                'Nama Pegawai Protokol',
                'Nomor WhatsApp',
                'Tugas Dibayar (Hari Kerja)',
                'Tugas Dibayar (Weekend/Libur)',
                'Tugas Tidak Dibayar',
                'Dinas Luar Kota (Trip)',
                'Total Penugasan',
                'Status Keseimbangan (Fairness)',
            ],
        ];
    }

    public function map($protokol): array
    {
        $this->rowNumber++;

        $totalPaid = $protokol->tugas_dibayar_workday + $protokol->tugas_dibayar_offday;
        $total = $protokol->total_tugas_bulan_ini;

        $status = 'Seimbang';
        if ($total == 0) {
            $status = 'Belum Bertugas (Prioritas)';
        } elseif ($protokol->tugas_dibayar_offday == 0) {
            $status = 'Prioritas Weekend';
        } elseif ($totalPaid >= 3) {
            $status = 'Sangat Seimbang';
        }

        return [
            $this->rowNumber,
            $protokol->nip ?? '-',
            $protokol->name,
            $protokol->phone_wa,
            $protokol->tugas_dibayar_workday . 'x',
            $protokol->tugas_dibayar_offday . 'x',
            $protokol->tugas_unpaid . 'x',
            $protokol->trip_luar_kota . 'x',
            $total . 'x',
            $status,
        ];
    }

    public function title(): string
    {
        return "Rekap {$this->month}-{$this->year}";
    }
}
