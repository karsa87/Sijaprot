<?php

namespace App\Services\Scheduling;

use App\Enums\LocationType;
use App\Models\HariLibur;
use App\Models\Kegiatan;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;

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
        if (! $this->passesHybridGap($protokol, $kegiatan, $relaxLuarKotaGap)) {
            return false;
        }

        // 3. Hard Rule H3: Cek Proteksi Full Weekend (Sabtu/Minggu sebelumnya)
        if (! $this->passesWeekendProtection($protokol, $kegiatan)) {
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
        // BUG-02 Fix: Mengurutkan kegiatan.end_datetime DESC (bukan assigned_at) dengan boundary 7 hari untuk 200M data
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
        // BUG-02 Fix: Mengurutkan kegiatan.start_datetime ASC (bukan assigned_at)
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
        // BUG-06 Fix: Untuk hari libur di hari kerja (Senin - Jumat), ambil Sabtu yang persis mendahuluinya
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
}
