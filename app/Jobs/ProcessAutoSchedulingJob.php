<?php

namespace App\Jobs;

use App\Models\Kegiatan;
use App\Services\Scheduling\SchedulingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutoSchedulingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Kegiatan $kegiatan;

    public ?int $assignedByUserId;

    /**
     * Jumlah percobaan maksimal jika terjadi deadlock / timeout.
     */
    public int $tries = 3;

    public function __construct(Kegiatan $kegiatan, ?int $assignedByUserId = null)
    {
        $this->onQueue('scheduling');
        $this->kegiatan = $kegiatan;
        $this->assignedByUserId = $assignedByUserId;
    }

    public function handle(SchedulingEngine $engine): void
    {
        Log::info("Memulai pemrosesan antrian auto-scheduling untuk kegiatan ID: {$this->kegiatan->id} ({$this->kegiatan->title})");

        $assigned = $engine->assignAndCommit($this->kegiatan, null, $this->assignedByUserId);

        Log::info("Auto-scheduling selesai untuk kegiatan ID: {$this->kegiatan->id}. Terpilih {$assigned->count()} personil protokol.");
    }
}
