<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Rekapitulasi Tugas Protokol - Kemendikdasmen</title>
    <style>
        @page {
            margin: 1cm 1.2cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.35;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .header h1 {
            font-size: 12pt;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header h2 {
            font-size: 10pt;
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
            font-size: 10.5pt;
            text-transform: uppercase;
            color: #1A5276;
        }

        .title-block span {
            font-size: 8.5pt;
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 7.5pt;
        }

        th {
            background-color: #1A5276;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            padding: 5px 2px;
            border: 1px solid #0f3650;
            vertical-align: middle;
        }

        td {
            padding: 4px 3px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .badge {
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-optimal {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-neutral {
            background-color: #f1f5f9;
            color: #334155;
        }

        .badge-warn {
            background-color: #fef3c7;
            color: #92400e;
        }

        .footer-sig {
            width: 100%;
            margin-top: 30px;
            font-size: 9pt;
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

    <!-- TABLE REKAP -->
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2">Nama Pegawai Protokol</th>
                <th colspan="2">Dibayar</th>
                <th colspan="2">Tidak Dibayar</th>
                <th rowspan="2" style="width: 48px;">Total Weekday</th>
                <th rowspan="2" style="width: 48px;">Total Weekend</th>
                <th rowspan="2" style="width: 45px;">Dalam Kota</th>
                <th rowspan="2" style="width: 45px;">Luar Kota</th>
                <th rowspan="2" style="width: 45px;">Total Bertugas</th>
            </tr>
            <tr>
                <th style="width: 40px;">Hari Kerja</th>
                <th style="width: 40px;">Hari Libur</th>
                <th style="width: 40px;">Hari Kerja</th>
                <th style="width: 40px;">Hari Libur</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($protokols as $idx => $p)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold">
                        {{ $p->name }}
                        @if (!empty($p->nip))
                            <div style="font-size: 7pt; font-weight: normal; color: #64748b;">NIP. {{ $p->nip }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">{{ $p->tugas_dibayar_workday }}x</td>
                    <td class="text-center">{{ $p->tugas_dibayar_offday }}x</td>
                    <td class="text-center">{{ $p->tugas_unpaid_workday }}x</td>
                    <td class="text-center">{{ $p->tugas_unpaid_offday }}x</td>
                    <td class="text-center font-bold">{{ $p->tugas_workday }}x</td>
                    <td class="text-center font-bold">{{ $p->tugas_offday }}x</td>
                    <td class="text-center">{{ $p->trip_dalam_kota }}x</td>
                    <td class="text-center">{{ $p->trip_luar_kota }}x</td>
                    <td class="text-center font-bold" style="background-color: #f8fafc;">
                        {{ $p->total_tugas_bulan_ini }}x</td>
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
                <p style="margin-bottom: 50px;">Kasubbag Protokol Kemendikdasmen,</p>
                <p class="font-bold" style="text-decoration: underline;">Oki Putra Budiarahman, S.H.</p>
                <p>NIP. 198007302010121001</p>
            </td>
        </tr>
    </table>

</body>

</html>
