<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Rekapitulasi Tugas Protokol - Kemendikdasmen</title>
    <style>
        @page {
            margin: 0.8cm 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .header h1 {
            font-size: 11pt;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header h2 {
            font-size: 9.5pt;
            margin: 2px 0;
            font-weight: normal;
        }

        .header p {
            font-size: 7.5pt;
            margin: 0;
            color: #475569;
        }

        .title-block {
            text-align: center;
            margin-bottom: 12px;
        }

        .title-block h3 {
            margin: 0;
            font-size: 10pt;
            text-transform: uppercase;
            color: #1A5276;
        }

        .title-block span {
            font-size: 8pt;
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 7.5pt;
        }

        th {
            background-color: #1A5276;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            padding: 5px 3px;
            border: 1px solid #0f3650;
            vertical-align: middle;
        }

        td {
            padding: 4px 4px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .badge {
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 6pt;
            font-weight: bold;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-optimal {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-neutral {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .badge-warn {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-info {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .footer-sig {
            width: 100%;
            margin-top: 25px;
            font-size: 8.5pt;
        }

        .footer-sig td {
            border: none;
            padding: 0;
        }
    </style>
</head>

<body>

    <!-- KOP RESMI SURAT KEMENDIKDASMEN -->
    <div class="header">
        <h1>KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH</h1>
        <h2>BIRO UMUM DAN PENGADAAN BARANG DAN JASA</h2>
        <p>Jalan Jenderal Sudirman, Senayan, Jakarta 10270 | Laman: kemdikbud.go.id</p>
    </div>

    <!-- TITLE -->
    <div class="title-block">
        <h3>REKAPITULASI KESEIMBANGAN DISTRIBUSI TUGAS PROTOKOL</h3>
        <span>Periode: {{ $monthName }} {{ $year }}</span>
    </div>

    <!-- TABLE REKAP (7 METRIK SPESIFIK & STATUS KESEIMBANGAN TANPA NIP) -->
    <table>
        <thead>
            <tr>
                <th style="width: 22px;">No</th>
                <th style="width: 115px; text-align: left; padding-left: 5px;">Nama Protokol</th>
                <th style="width: 44px;">Tidak Dibayar</th>
                <th style="width: 44px;">Dibayar</th>
                <th>Kunker (Total & Rincian Agenda)</th>
                <th style="width: 38px;">WFA (Jum)</th>
                <th style="width: 44px;">Hari Weekend</th>
                <th style="width: 44px;">Hari Weekday</th>
                <th style="width: 38px;">Total Tugas</th>
                <th style="width: 82px;">Status Keseimbangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($protokols as $idx => $p)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="font-bold">{{ $p->name }}</div>
                        <div style="font-size: 6.5pt; color: #64748b;">{{ $p->phone_wa }}</div>
                    </td>
                    <td class="text-center">{{ $p->tugas_unpaid ?? 0 }}x</td>
                    <td class="text-center font-bold" style="color: #047857;">{{ $p->tugas_dibayar_total ?? 0 }}x</td>
                    <td>
                        <div class="font-bold">{{ $p->tugas_kunker ?? 0 }}x</div>
                        @if ($p->kegiatan && $p->kegiatan->isNotEmpty())
                            <div style="margin-top: 2px; font-size: 6.5pt; color: #334155; line-height: 1.25;">
                                @foreach ($p->kegiatan as $k)
                                    <div>• {{ \Carbon\Carbon::parse($k->start_datetime)->format('d/m') }}: {{ $k->title }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="text-center font-bold" style="color: #0284c7;">{{ $p->tugas_wfa ?? 0 }}x</td>
                    <td class="text-center font-bold" style="color: #b45309;">{{ $p->tugas_offday ?? 0 }}x</td>
                    <td class="text-center">{{ $p->tugas_workday ?? 0 }}x</td>
                    <td class="text-center font-bold" style="background-color: #f8fafc;">
                        {{ $p->total_tugas_bulan_ini ?? 0 }}x
                    </td>
                    <td class="text-center">
                        <span class="badge badge-{{ $p->status_keseimbangan['badge'] ?? 'neutral' }}">
                            {{ $p->status_keseimbangan['label'] ?? 'Seimbang' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- SIGNATURE BOX -->
    <table class="footer-sig">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: center;">
                <p>Jakarta, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
                <p style="margin-bottom: 45px;">Kasubbag Protokol Kemendikdasmen,</p>
                <p class="font-bold" style="text-decoration: underline;">Oki Putra Budiarahman, S.H.</p>
                <p>NIP. 198007302010121001</p>
            </td>
        </tr>
    </table>

</body>

</html>
