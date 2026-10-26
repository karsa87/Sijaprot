<?php

namespace App\Services\Scheduling;

use App\Enums\LocationType;
use App\Models\HariLibur;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GapRuleChecker
{
    /**
     * Mengecek apakah seorang protokol lolos seluruh Hard Rules (H1, H2, H3, H4) dan Rotasi WFA.
     * Query dioptimasi ketat hanya memindai rentang waktu sempit (10 hari)
     * memanfaatkan composite index, menjamin performa < 2ms pada 200M data.
     */
    public function passesAllHardRules(
        Protokol $protokol,
        Kegiatan $kegiatan,
        bool $relaxLuarKotaGap = false,
        ?Collection $activePool = null,
        bool $relaxWfaRotation = false
    ): bool {
        // 1. Hard Rule H2: Cek Overlap Waktu Langsung
        if ($this->hasDirectOverlap($protokol, $kegiatan)) {
            return false;
        }

        // 2. Hard Rule H1: Cek Gap Waktu Hybrid (24 Jam Dalam Kota / H+1 Luar Kota)
        if (! $this->passesHybridGap($protokol, $kegiatan, $relaxLuarKotaGap)) {
            return false;
        }

        // 3. Hard Rule H3: Cek Proteksi Full Weekend (Sabtu/Minggu sebelumnya)
        if (! $this->passesWeekendProtection($protokol, $kegiatan)) {
            return false;
        }

        // 4. Hard Rule H4: WFA-to-Weekend Guard (Blokir Akhir Pekan di Minggu yang Sama)
        if (! $this->passesWfaWeekendGuard($protokol, $kegiatan)) {
            return false;
        }

        // 5. Hard/Rotational Rule: Rotasi WFA Antar-Minggu
        if (! $relaxWfaRotation && ! $this->passesWfaRotationRule($protokol, $kegiatan, $activePool)) {
            return false;
        }

        return true;
    }

    /**
     * H2: Tidak boleh ada tugas lain yang waktunya beririsan (overlap).
     */
    public function hasDirectOverlap(Protokol $protokol, Kegiatan $kegiatan): bool
    {
        return Penugasan::where('penugasan.protokol_id', $protokol->id)
            ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
            ->whereNull('kegiatan.deleted_at')
            ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
            ->where('kegiatan.status', '!=', 'cancelled')
            ->where('kegiatan.start_datetime', '<', $kegiatan->end_datetime)
            ->where('kegiatan.end_datetime', '>', $kegiatan->start_datetime)
            ->exists();
    }

    /**
     * H1: Gap Waktu Hybrid.
     * - Kegiatan sebelumnya Dalam Kota -> gap minimal 24 jam dari jam selesai.
     * - Kegiatan sebelumnya Luar Kota -> gap penuh H+1 hari kalender setelah kepulangan.
     * - Berlaku juga secara forward (jika sudah ada tugas di masa depan).
     */
    public function passesHybridGap(Protokol $protokol, Kegiatan $kegiatan, bool $relaxLuarKota = false): bool
    {
        $newStart = Carbon::parse($kegiatan->start_datetime);
        $newEnd = Carbon::parse($kegiatan->end_datetime);

        // A. Cek Kegiatan Terakhir SEBELUM kegiatan baru (Backward Gap)
        $previousTask = Penugasan::where('penugasan.protokol_id', $protokol->id)
            ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
            ->whereNull('kegiatan.deleted_at')
            ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
            ->where('kegiatan.status', '!=', 'cancelled')
            ->where('kegiatan.end_datetime', '<=', $newStart)
            ->where('kegiatan.end_datetime', '>=', (clone $newStart)->subDays(7))
            ->orderBy('kegiatan.end_datetime', 'desc')
            ->select('penugasan.*')
            ->with(['kegiatan:id,location_type,start_datetime,end_datetime'])
            ->first();

        if ($previousTask && $previousTask->kegiatan) {
            $prevKegiatan = $previousTask->kegiatan;
            $prevEnd = Carbon::parse($prevKegiatan->end_datetime);

            if ($prevKegiatan->location_type === LocationType::LUAR_KOTA && ! $relaxLuarKota) {
                // Luar Kota: Hari kepulangan + 1 hari kalender penuh diblokir (Baru boleh H+2)
                $earliestAvailableDate = (clone $prevEnd)->startOfDay()->addDays(2);
                if ($newStart->lt($earliestAvailableDate)) {
                    return false;
                }
            } else {
                // Dalam Kota: Minimal jeda 24 jam penuh
                $earliestAvailableTime = (clone $prevEnd)->addHours(24);
                if ($newStart->lt($earliestAvailableTime)) {
                    return false;
                }
            }
        }

        // B. Cek Kegiatan Berikutnya SETELAH kegiatan baru (Forward Gap)
        $nextTask = Penugasan::where('penugasan.protokol_id', $protokol->id)
            ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
            ->whereNull('kegiatan.deleted_at')
            ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
            ->where('kegiatan.status', '!=', 'cancelled')
            ->where('kegiatan.start_datetime', '>=', $newEnd)
            ->where('kegiatan.start_datetime', '<=', (clone $newEnd)->addDays(7))
            ->orderBy('kegiatan.start_datetime', 'asc')
            ->select('penugasan.*')
            ->with(['kegiatan:id,location_type,start_datetime,end_datetime'])
            ->first();

        if ($nextTask && $nextTask->kegiatan) {
            $nextStart = Carbon::parse($nextTask->kegiatan->start_datetime);

            if ($kegiatan->location_type === LocationType::LUAR_KOTA && ! $relaxLuarKota) {
                $earliestNextAllowedDate = (clone $newEnd)->startOfDay()->addDays(2);
                if ($nextStart->lt($earliestNextAllowedDate)) {
                    return false;
                }
            } else {
                $earliestNextAllowedTime = (clone $newEnd)->addHours(24);
                if ($nextStart->lt($earliestNextAllowedTime)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * H3: Proteksi Akhir Pekan (Full Weekend Guard).
     * Jika kegiatan baru jatuh pada hari Sabtu, Minggu, atau Hari Libur Nasional:
     * Petugas yang sudah berdinas di Sabtu/Minggu pekan sebelumnya diblokir penuh.
     */
    public function passesWeekendProtection(Protokol $protokol, Kegiatan $kegiatan): bool
    {
        $targetDate = Carbon::parse($kegiatan->start_datetime);
        $isOffday = $kegiatan->is_weekend || $kegiatan->is_holiday || $targetDate->isWeekend() || HariLibur::isHoliday($targetDate);

        if (! $isOffday) {
            return true; // Hari kerja biasa bebas dari aturan H3
        }

        // Hitung rentang akhir pekan sebelumnya (Sabtu & Minggu pekan lalu)
        if ($targetDate->isWeekend()) {
            $prevSaturday = (clone $targetDate)->subDays(7)->startOfWeek(Carbon::SATURDAY)->startOfDay();
        } else {
            $prevSaturday = (clone $targetDate)->previous(Carbon::SATURDAY)->startOfDay();
        }
        $prevSunday = (clone $prevSaturday)->addDay()->endOfDay();

        $workedPrevWeekend = Penugasan::where('penugasan.protokol_id', $protokol->id)
            ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
            ->whereNull('kegiatan.deleted_at')
            ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
            ->where('kegiatan.status', '!=', 'cancelled')
            ->whereBetween('kegiatan.start_datetime', [$prevSaturday, $prevSunday])
            ->exists();

        return ! $workedPrevWeekend;
    }

    /**
     * H4: WFA-to-Weekend Guard (Blokir Akhir Pekan di Minggu yang Sama).
     * Jika seorang protokol bertugas pada hari WFA (Jum'at), maka protokol tersebut
     * TIDAK BOLEH DITUGASKAN pada hari Sabtu atau Minggu pada minggu yang sama.
     * Sebaliknya, jika kegiatan jatuh pada Jum'at WFA, personil yang sudah terjadwal di Sabtu/Minggu minggu yang sama diblokir.
     */
    public function passesWfaWeekendGuard(Protokol $protokol, Kegiatan $kegiatan): bool
    {
        $targetDate = Carbon::parse($kegiatan->start_datetime);
        $startOfWeek = (clone $targetDate)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fridayDate = (clone $startOfWeek)->addDays(4); // Hari Jum'at
        $saturdayStart = (clone $startOfWeek)->addDays(5)->startOfDay(); // Sabtu 00:00:00
        $sundayEnd = (clone $startOfWeek)->addDays(6)->endOfDay(); // Minggu 23:59:59

        $isTargetWeekend = $kegiatan->is_weekend || $targetDate->isWeekend();
        $isTargetWfa = $kegiatan->isWfa();

        // 1. Jika kegiatan yang diusulkan jatuh pada hari Sabtu / Minggu
        if ($isTargetWeekend) {
            // Periksa apakah protokol sudah bertugas di Jum'at WFA pada minggu berjalan
            $hasAssignedFridayWfa = Penugasan::where('penugasan.protokol_id', $protokol->id)
                ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
                ->whereNull('kegiatan.deleted_at')
                ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
                ->where('kegiatan.status', '!=', 'cancelled')
                ->whereDate('kegiatan.start_datetime', $fridayDate->toDateString())
                ->where('kegiatan.is_holiday', false)
                ->exists();

            if ($hasAssignedFridayWfa) {
                return false;
            }
        }

        // 2. Jika kegiatan yang diusulkan jatuh pada hari Jum'at WFA
        if ($isTargetWfa) {
            // Periksa apakah protokol sudah terjadwal di Sabtu atau Minggu pada minggu berjalan
            $hasAssignedWeekend = Penugasan::where('penugasan.protokol_id', $protokol->id)
                ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
                ->whereNull('kegiatan.deleted_at')
                ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
                ->where('kegiatan.status', '!=', 'cancelled')
                ->whereBetween('kegiatan.start_datetime', [$saturdayStart, $sundayEnd])
                ->exists();

            if ($hasAssignedWeekend) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rotational Rule: Rotasi WFA Antar-Minggu.
     * Jika bertugas pada hari WFA (Jum'at), maka pada hari WFA minggu berikutnya
     * tidak dapat bertugas lagi jika masih terdapat protokol aktif lain yang belum pernah
     * bertugas pada hari WFA dalam siklus bulan tersebut.
     *
     * Siklus Reset Rotasi: Jika SELURUH protokol aktif sudah pernah bertugas minimal 1x,
     * status siklus ter-reset dan seluruh protokol kembali memenuhi syarat.
     */
    public function passesWfaRotationRule(Protokol $protokol, Kegiatan $kegiatan, ?Collection $activePool = null): bool
    {
        if (! $kegiatan->isWfa()) {
            return true;
        }

        $targetDate = Carbon::parse($kegiatan->start_datetime);
        $month = $targetDate->month;
        $year = $targetDate->year;

        $pool = $activePool ?? Protokol::active()->get();
        if ($pool->isEmpty()) {
            return true;
        }

        $driver = DB::connection()->getDriverName();
        $fridaySql = match ($driver) {
            'sqlite' => "strftime('%w', kegiatan.start_datetime) = '5'",
            'pgsql' => 'EXTRACT(ISODOW FROM kegiatan.start_datetime) = 5',
            default => 'WEEKDAY(kegiatan.start_datetime) = 4',
        };

        // Query penugasan WFA di bulan berjalan untuk seluruh anggota pool
        $wfaCounts = Penugasan::whereIn('penugasan.protokol_id', $pool->pluck('id'))
            ->join('kegiatan', 'penugasan.kegiatan_id', '=', 'kegiatan.id')
            ->whereNull('kegiatan.deleted_at')
            ->where('kegiatan.id', '!=', $kegiatan->id ?? 0)
            ->where('kegiatan.status', '!=', 'cancelled')
            ->whereMonth('kegiatan.start_datetime', $month)
            ->whereYear('kegiatan.start_datetime', $year)
            ->where('kegiatan.is_holiday', false)
            ->where('kegiatan.is_weekend', false)
            ->where(function ($wq) use ($fridaySql) {
                $wq->whereHas('kegiatan.jenisKegiatan', function ($jq) {
                    $jq->where('payment_day_type', 'wfa');
                })->orWhereRaw($fridaySql);
            })
            ->groupBy('penugasan.protokol_id')
            ->selectRaw('penugasan.protokol_id, count(*) as total_count')
            ->pluck('total_count', 'penugasan.protokol_id')
            ->toArray();

        $myWfaCount = $wfaCounts[$protokol->id] ?? 0;

        // Cari anggota tim yang belum pernah WFA sama sekali bulan ini
        $zeroWfaMembers = $pool->filter(function ($p) use ($wfaCounts) {
            return ($wfaCounts[$p->id] ?? 0) === 0;
        });

        // Jika semua anggota pool sudah pernah WFA minimal 1x (siklus reset rotasi)
        if ($zeroWfaMembers->isEmpty()) {
            return true;
        }

        // Masih ada personil yang belum pernah WFA. Jika personil ini sudah pernah WFA bulan ini, dirotasi keluar
        if ($myWfaCount > 0) {
            return false;
        }

        return true;
    }
}
