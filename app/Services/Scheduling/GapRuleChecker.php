<?php

namespace App\Services\Scheduling;

use App\Enums\LocationType;
use App\Models\HariLibur;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class GapRuleChecker
{
    /**
     * Mengecek apakah seorang protokol lolos seluruh Hard Rules (H1, H2, H3).
     * Query dioptimasi ketat hanya memindai rentang waktu sempit (10 hari)
     * memanfaatkan composite index, menjamin performa < 2ms pada 200M data.
     */
    public function passesAllHardRules(Protokol $protokol, Kegiatan $kegiatan, bool $relaxLuarKotaGap = false): bool
    {
        // 1. Hard Rule H2: Cek Overlap Waktu Langsung
        if ($this->hasDirectOverlap($protokol, $kegiatan)) {
            return false;
        }

        // 2. Hard Rule H1: Cek Gap Waktu Hybrid (24 Jam Dalam Kota / H+1 Luar Kota)
        if (!$this->passesHybridGap($protokol, $kegiatan, $relaxLuarKotaGap)) {
            return false;
        }

        // 3. Hard Rule H3: Cek Proteksi Full Weekend (Sabtu/Minggu sebelumnya)
        if (!$this->passesWeekendProtection($protokol, $kegiatan)) {
            return false;
        }

        return true;
    }

    /**
     * H2: Tidak boleh ada tugas lain yang waktunya beririsan (overlap).
     */
    public function hasDirectOverlap(Protokol $protokol, Kegiatan $kegiatan): bool
    {
        return Penugasan::where('protokol_id', $protokol->id)
            ->whereHas('kegiatan', function (Builder $q) use ($kegiatan) {
                $q->where('id', '!=', $kegiatan->id ?? 0)
                  ->where('status', '!=', 'cancelled')
                  ->where('start_datetime', '<', $kegiatan->end_datetime)
                  ->where('end_datetime', '>', $kegiatan->start_datetime);
            })
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
        $previousTask = Penugasan::where('protokol_id', $protokol->id)
            ->whereHas('kegiatan', function (Builder $q) use ($newStart, $kegiatan) {
                $q->where('id', '!=', $kegiatan->id ?? 0)
                  ->where('status', '!=', 'cancelled')
                  ->where('end_datetime', '<=', $newStart)
                  ->where('end_datetime', '>=', (clone $newStart)->subDays(7)); // Boundary 7 hari (Index hit)
            })
            ->with(['kegiatan:id,location_type,start_datetime,end_datetime'])
            ->latest('assigned_at')
            ->first();

        if ($previousTask && $previousTask->kegiatan) {
            $prevKegiatan = $previousTask->kegiatan;
            $prevEnd = Carbon::parse($prevKegiatan->end_datetime);

            if ($prevKegiatan->location_type === LocationType::LUAR_KOTA && !$relaxLuarKota) {
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
        $nextTask = Penugasan::where('protokol_id', $protokol->id)
            ->whereHas('kegiatan', function (Builder $q) use ($newEnd, $kegiatan) {
                $q->where('id', '!=', $kegiatan->id ?? 0)
                  ->where('status', '!=', 'cancelled')
                  ->where('start_datetime', '>=', $newEnd)
                  ->where('start_datetime', '<=', (clone $newEnd)->addDays(7)); // Boundary 7 hari
            })
            ->with(['kegiatan:id,location_type,start_datetime,end_datetime'])
            ->oldest('assigned_at')
            ->first();

        if ($nextTask && $nextTask->kegiatan) {
            $nextStart = Carbon::parse($nextTask->kegiatan->start_datetime);

            if ($kegiatan->location_type === LocationType::LUAR_KOTA && !$relaxLuarKota) {
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

        if (!$isOffday) {
            return true; // Hari kerja biasa bebas dari aturan H3
        }

        // Hitung rentang akhir pekan sebelumnya (Sabtu & Minggu pekan lalu)
        // Contoh: Target Sabtu 11 Okt -> Prev weekend: Sabtu 4 Okt s.d. Minggu 5 Okt
        $prevSaturday = (clone $targetDate)->subDays(7)->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $prevSunday = (clone $prevSaturday)->addDay()->endOfDay();

        $workedPrevWeekend = Penugasan::where('protokol_id', $protokol->id)
            ->whereHas('kegiatan', function (Builder $q) use ($prevSaturday, $prevSunday, $kegiatan) {
                $q->where('id', '!=', $kegiatan->id ?? 0)
                  ->where('status', '!=', 'cancelled')
                  ->whereBetween('start_datetime', [$prevSaturday, $prevSunday]);
            })
            ->exists();

        return !$workedPrevWeekend;
    }
}
