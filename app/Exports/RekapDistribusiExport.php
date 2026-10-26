<?php

namespace App\Exports;

use App\Models\Protokol;
use App\Services\Scheduling\WorkloadLevelingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapDistribusiExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected int $month;

    protected int $year;

    protected int $rowNumber = 0;

    protected ?Collection $cachedCollection = null;

    protected ?array $teamFairness = null;

    public function __construct(int $month, int $year)
    {
        $this->month = $month;
        $this->year = $year;
    }

    public function collection(): Collection
    {
        if ($this->cachedCollection !== null) {
            return $this->cachedCollection;
        }

        $protokols = Protokol::active()
            ->withMonthlyMetrics($this->month, $this->year)
            ->with(['kegiatan' => function ($kq) {
                $kq->whereMonth('start_datetime', $this->month)
                    ->whereYear('start_datetime', $this->year)
                    ->where('status', '!=', 'cancelled')
                    ->where('is_kunker', true)
                    ->select(['kegiatan.id', 'kegiatan.title', 'kegiatan.start_datetime'])
                    ->orderBy('kegiatan.start_datetime', 'asc');
            }])
            ->orderBy('name')
            ->get();

        $this->teamFairness = app(WorkloadLevelingService::class)->evaluateTeam($protokols);

        foreach ($protokols as $p) {
            $p->level_beban = $this->teamFairness['evaluations'][$p->id] ?? null;
        }

        $this->cachedCollection = $protokols;

        return $this->cachedCollection;
    }

    public function headings(): array
    {
        if ($this->cachedCollection === null) {
            $this->collection();
        }

        $monthName = Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');
        $tf = $this->teamFairness;
        $statusLine = '[STATUS TIM: '.strtoupper($tf['status_label'] ?? 'SANGAT SEIMBANG').' (Indeks: '.($tf['indeks'] ?? 100).'%)]';
        $summaryLine = ($tf['summary_text'] ?? '').' — '.($tf['diagnosa_text'] ?? '');

        return [
            ['KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH RI'],
            ["REKAPITULASI KESEIMBANGAN DISTRIBUSI TUGAS PROTOKOL - {$monthName}"],
            [$statusLine],
            [$summaryLine],
            [],
            [
                'No',
                'Nama Protokol',
                'Nomor WhatsApp',
                'Tidak Dibayar',
                'Dibayar',
                'Kemungkinan Dibayar',
                'Kunker (Total & Rincian Agenda)',
                'WFA (Jum\'at)',
                'Hari Weekend',
                'Hari Weekday',
                'Total Tugas',
                'Status Beban Kerja',
                'Catatan Diagnosa',
            ],
        ];
    }

    public function map($protokol): array
    {
        $this->rowNumber++;

        $kunkerDetails = [];
        if ($protokol->kegiatan && $protokol->kegiatan->isNotEmpty()) {
            foreach ($protokol->kegiatan as $k) {
                $dateFormatted = Carbon::parse($k->start_datetime)->format('d/m');
                $kunkerDetails[] = "• {$dateFormatted}: {$k->title}";
            }
        }

        $kunkerText = ($protokol->tugas_kunker ?? 0).'x';
        if (! empty($kunkerDetails)) {
            $kunkerText .= "\n".implode("\n", $kunkerDetails);
        }

        $paidTasks = $protokol->tugas_paid ?? (($protokol->tugas_dibayar_workday ?? 0) + ($protokol->tugas_dibayar_offday ?? 0));
        $statusLevel = $protokol->level_beban['label'] ?? ($protokol->status_keseimbangan['label'] ?? 'Seimbang');
        $diagnosa = $protokol->level_beban['diagnosa'] ?? '-';

        return [
            $this->rowNumber,
            $protokol->name,
            $protokol->phone_wa,
            ($protokol->tugas_unpaid ?? 0).'x',
            ($paidTasks ?? 0).'x',
            ($protokol->tugas_conditional ?? 0).'x',
            $kunkerText,
            ($protokol->tugas_wfa ?? 0).'x',
            ($protokol->tugas_offday ?? 0).'x',
            ($protokol->tugas_workday ?? 0).'x',
            ($protokol->total_tugas_bulan_ini ?? 0).'x',
            $statusLevel,
            $diagnosa,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('G')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A3:A4')->getFont()->setBold(true);
        $sheet->getStyle('A6:M6')->getFont()->setBold(true);
        $sheet->getStyle('L')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function title(): string
    {
        return "Rekap {$this->month}-{$this->year}";
    }
}
