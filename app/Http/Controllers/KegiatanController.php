<?php

namespace App\Http\Controllers;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Protokol;
use App\Services\Scheduling\GapRuleChecker;
use App\Services\Scheduling\SchedulingEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanController extends Controller
{
    /**
     * Display create form for new kegiatan.
     */
    public function create(Request $request): Response
    {
        $targetDate = $request->input('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : Carbon::now()->addDay()->toDateString();

        $jenisList = JenisKegiatan::query()->orderBy('name')->get();
        $activeProtokols = Protokol::active()->orderBy('name')->get(['id', 'name', 'phone_wa', 'status']);

        // Holidays for fast client-side weekend/holiday auto detection
        $holidays = HariLibur::query()
            ->whereYear('tanggal', '>=', Carbon::now()->year - 1)
            ->get(['tanggal', 'name', 'type']);

        return Inertia::render('Kegiatan/FormKegiatan', [
            'isEditing' => false,
            'kegiatan' => null,
            'defaultDate' => $targetDate,
            'jenisList' => $jenisList,
            'activeProtokols' => $activeProtokols,
            'holidays' => $holidays,
        ]);
    }

    /**
     * Display edit form for existing kegiatan.
     */
    public function edit(Kegiatan $kegiatan): Response
    {
        $kegiatan->load([
            'penugasan.protokol:id,name,phone_wa,status',
            'jenisKegiatan:id,name,code,location_type,payment_type,payment_day_type,is_kunker',
        ]);

        $jenisList = JenisKegiatan::query()->orderBy('name')->get();
        $activeProtokols = Protokol::active()->orderBy('name')->get(['id', 'name', 'phone_wa', 'status']);

        $holidays = HariLibur::query()
            ->whereYear('tanggal', '>=', Carbon::now()->year - 1)
            ->get(['tanggal', 'name', 'type']);

        // Format assignments as recommendation items
        $existingRecs = [];
        foreach ($kegiatan->penugasan as $pen) {
            if ($pen->protokol) {
                $existingRecs[] = [
                    'protokol' => $pen->protokol->toArray(),
                    'score' => (float) ($pen->score_snapshot ?? 80.00),
                    'reason' => $pen->selection_reason ?? ($pen->is_override ? "Override Manual: {$pen->override_reason}" : 'Petugas Ditugaskan'),
                ];
            }
        }

        return Inertia::render('Kegiatan/FormKegiatan', [
            'isEditing' => true,
            'kegiatan' => [
                'id' => $kegiatan->id,
                'title' => $kegiatan->title,
                'jenis_kegiatan_id' => $kegiatan->jenis_kegiatan_id,
                'jumlah_protokol' => $kegiatan->jumlah_protokol,
                'start_date' => $kegiatan->start_datetime->toDateString(),
                'start_time' => $kegiatan->start_datetime->format('H:i'),
                'end_date' => $kegiatan->end_datetime->toDateString(),
                'end_time' => $kegiatan->end_datetime->format('H:i'),
                'is_kunker' => (bool) $kegiatan->is_kunker,
                'is_wfa' => $kegiatan->isWfa(),
                'detail' => $kegiatan->detail ?? '',
                'recommendations' => $existingRecs,
            ],
            'defaultDate' => $kegiatan->start_datetime->toDateString(),
            'jenisList' => $jenisList,
            'activeProtokols' => $activeProtokols,
            'holidays' => $holidays,
        ]);
    }

    /**
     * Run Auto-Scheduler algorithm simulation (in-memory recommendation).
     * Protected and executed on server with SchedulingEngine.
     */
    public function simulate(Request $request, SchedulingEngine $engine): JsonResponse
    {
        $validated = $this->validateKegiatanPayload($request);

        $tempKegiatan = $this->buildTemporaryKegiatan($validated, $request->input('kegiatan_id'));

        $output = $engine->generateRecommendation($tempKegiatan);

        return response()->json([
            'recommendations' => $output['recommendations']->toArray(),
            'relaxation_level' => $output['relaxation_level'],
            'warning' => $output['warning'],
            'total_eligible' => $output['total_eligible'],
        ]);
    }

    /**
     * Validate direct overlap when swapping or manually adding protocol.
     */
    public function validateOverlap(Request $request, GapRuleChecker $gapChecker): JsonResponse
    {
        $request->validate([
            'protokol_id' => ['required', 'exists:protokols,id'],
            'kegiatan_id' => ['nullable', 'exists:kegiatan,id'],
            'start_date' => ['required_without:kegiatan_id', 'nullable', 'date'],
            'start_time' => ['required_without:kegiatan_id', 'nullable', 'date_format:H:i'],
            'end_date' => ['required_without:kegiatan_id', 'nullable', 'date'],
            'end_time' => ['required_without:kegiatan_id', 'nullable', 'date_format:H:i'],
            'jenis_kegiatan_id' => ['nullable', 'exists:jenis_kegiatan,id'],
        ]);

        $proto = Protokol::findOrFail($request->input('protokol_id'));
        $tempKegiatan = $this->buildKegiatanForOverlap($request);

        $hasOverlap = $gapChecker->hasDirectOverlap($proto, $tempKegiatan);

        if ($hasOverlap) {
            return response()->json([
                'valid' => false,
                'message' => "Pegawai {$proto->name} sudah memiliki jadwal kegiatan lain di tanggal dan waktu yang sama.",
            ], 422);
        }

        return response()->json(['valid' => true]);
    }

    /**
     * Store newly created kegiatan and commit protocol assignments atomically.
     */
    public function store(Request $request, SchedulingEngine $engine): RedirectResponse
    {
        $validated = $this->validateKegiatanPayload($request);

        $jenis = JenisKegiatan::findOrFail($validated['jenis_kegiatan_id']);
        $startDateTime = "{$validated['start_date']} {$validated['start_time']}:00";
        $endDateTime = "{$validated['end_date']} {$validated['end_time']}:00";

        // Multi-day & weekend detection
        $isWeekend = Carbon::parse($validated['start_date'])->isWeekend() || Carbon::parse($validated['end_date'])->isWeekend();
        $isHoliday = HariLibur::isHoliday(Carbon::parse($validated['start_date'])) || HariLibur::isHoliday(Carbon::parse($validated['end_date']));
        $isKunker = isset($validated['is_kunker']) ? (bool) $validated['is_kunker'] : $jenis->isKunker();

        $kegiatan = Kegiatan::create([
            'jenis_kegiatan_id' => $jenis->id,
            'title' => $validated['title'],
            'detail' => $validated['detail'] ?? null,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'jumlah_protokol' => $validated['jumlah_protokol'],
            'is_weekend' => $isWeekend,
            'is_holiday' => $isHoliday,
            'is_kunker' => $isKunker,
            'location_type' => $jenis->location_type->value,
            'status' => KegiatanStatus::DRAFT->value,
            'created_by' => Auth::id(),
        ]);

        $chosenIds = null;
        if (! empty($request->input('recommendations'))) {
            $chosenIds = collect($request->input('recommendations'))
                ->pluck('protokol.id')
                ->filter()
                ->toArray();
        }

        $engine->assignAndCommit($kegiatan, $chosenIds, Auth::id());

        return redirect()->route('dashboard')
            ->with('success', "Kegiatan '{$kegiatan->title}' berhasil dijadwalkan dan diterbitkan!");
    }

    /**
     * Update existing kegiatan and re-commit protocol assignments atomically.
     */
    public function update(Request $request, Kegiatan $kegiatan, SchedulingEngine $engine): RedirectResponse
    {
        $validated = $this->validateKegiatanPayload($request);

        $jenis = JenisKegiatan::findOrFail($validated['jenis_kegiatan_id']);
        $startDateTime = "{$validated['start_date']} {$validated['start_time']}:00";
        $endDateTime = "{$validated['end_date']} {$validated['end_time']}:00";

        $isWeekend = Carbon::parse($validated['start_date'])->isWeekend() || Carbon::parse($validated['end_date'])->isWeekend();
        $isHoliday = HariLibur::isHoliday(Carbon::parse($validated['start_date'])) || HariLibur::isHoliday(Carbon::parse($validated['end_date']));
        $isKunker = isset($validated['is_kunker']) ? (bool) $validated['is_kunker'] : (bool) $kegiatan->is_kunker;

        $kegiatan->update([
            'jenis_kegiatan_id' => $jenis->id,
            'title' => $validated['title'],
            'detail' => $validated['detail'] ?? null,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'jumlah_protokol' => $validated['jumlah_protokol'],
            'is_weekend' => $isWeekend,
            'is_holiday' => $isHoliday,
            'is_kunker' => $isKunker,
            'location_type' => $jenis->location_type->value,
        ]);

        $chosenIds = null;
        if (! empty($request->input('recommendations'))) {
            $chosenIds = collect($request->input('recommendations'))
                ->pluck('protokol.id')
                ->filter()
                ->toArray();

            // Hapus penugasan untuk protokol yang tidak lagi terpilih
            $kegiatan->penugasan()->whereNotIn('protokol_id', $chosenIds)->delete();
        }

        $engine->assignAndCommit($kegiatan, $chosenIds, Auth::id());

        return redirect()->route('dashboard')
            ->with('success', "Kegiatan '{$kegiatan->title}' berhasil diperbarui!");
    }

    /**
     * Soft delete kegiatan (SEC-02).
     */
    public function destroy(Kegiatan $kegiatan): RedirectResponse
    {
        $title = $kegiatan->title;
        $kegiatan->delete();

        return redirect()->route('dashboard')
            ->with('success', "Kegiatan '{$title}' dan seluruh penugasan terkait berhasil dihapus.");
    }

    /**
     * Validate payload with BUG-03 negative duration prevention.
     */
    protected function validateKegiatanPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:255'],
            'jenis_kegiatan_id' => ['required', 'exists:jenis_kegiatan,id'],
            'jumlah_protokol' => ['required', 'integer', 'min:1', 'max:10'],
            'start_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                // BUG-03: Mencegah waktu selesai lebih awal atau sama dengan waktu mulai
                function ($attribute, $value, $fail) use ($request) {
                    $startDate = $request->input('start_date');
                    $startTime = $request->input('start_time');
                    $endTime = $request->input('end_time');
                    if ($startDate && $startTime && $endTime) {
                        $start = Carbon::parse("{$startDate} {$startTime}");
                        $end = Carbon::parse("{$value} {$endTime}");
                        if ($end->lte($start)) {
                            $fail('Waktu selesai kegiatan harus lebih akhir dari waktu mulai kegiatan.');
                        }
                    }
                },
            ],
            'end_time' => ['required', 'date_format:H:i'],
            'is_kunker' => ['nullable', 'boolean'],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Helper to build in-memory Kegiatan instance for simulation / validation.
     */
    protected function buildTemporaryKegiatan(array $data, ?int $kegiatanId = null): Kegiatan
    {
        $jenis = JenisKegiatan::find($data['jenis_kegiatan_id']);
        $startDateTime = "{$data['start_date']} {$data['start_time']}:00";
        $endDateTime = "{$data['end_date']} {$data['end_time']}:00";

        $isWeekend = Carbon::parse($data['start_date'])->isWeekend() || Carbon::parse($data['end_date'])->isWeekend();
        $isHoliday = HariLibur::isHoliday(Carbon::parse($data['start_date'])) || HariLibur::isHoliday(Carbon::parse($data['end_date']));
        $isKunker = isset($data['is_kunker']) ? (bool) $data['is_kunker'] : ($jenis?->isKunker() ?? false);

        return new Kegiatan([
            'id' => $kegiatanId,
            'jenis_kegiatan_id' => $data['jenis_kegiatan_id'],
            'title' => $data['title'],
            'detail' => $data['detail'] ?? null,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'jumlah_protokol' => $data['jumlah_protokol'],
            'is_weekend' => $isWeekend,
            'is_holiday' => $isHoliday,
            'is_kunker' => $isKunker,
            'location_type' => $jenis?->location_type->value ?? LocationType::DALAM_KOTA->value,
            'status' => KegiatanStatus::DRAFT->value,
        ]);
    }

    /**
     * Build minimal Kegiatan instance specifically for protocol overlap checking.
     */
    protected function buildKegiatanForOverlap(Request $request): Kegiatan
    {
        $kegiatanId = $request->filled('kegiatan_id') ? (int) $request->input('kegiatan_id') : null;
        $existing = $kegiatanId ? Kegiatan::find($kegiatanId) : null;

        if ($request->filled('start_date') && $request->filled('start_time')) {
            $startDateTime = "{$request->input('start_date')} {$request->input('start_time')}:00";
        } elseif ($request->filled('start_datetime')) {
            $startDateTime = Carbon::parse($request->input('start_datetime'))->toDateTimeString();
        } else {
            $startDateTime = $existing?->start_datetime ? Carbon::parse($existing->start_datetime)->toDateTimeString() : Carbon::now()->toDateTimeString();
        }

        if ($request->filled('end_date') && $request->filled('end_time')) {
            $endDateTime = "{$request->input('end_date')} {$request->input('end_time')}:00";
        } elseif ($request->filled('end_datetime')) {
            $endDateTime = Carbon::parse($request->input('end_datetime'))->toDateTimeString();
        } else {
            $endDateTime = $existing?->end_datetime ? Carbon::parse($existing->end_datetime)->toDateTimeString() : Carbon::now()->addHours(2)->toDateTimeString();
        }

        $kegiatan = new Kegiatan([
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'status' => KegiatanStatus::DRAFT->value,
        ]);

        if ($kegiatanId) {
            $kegiatan->id = $kegiatanId;
        }

        return $kegiatan;
    }
}
