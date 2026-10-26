<?php

namespace App\Http\Controllers\Master;

use App\Enums\ProtokolStatus;
use App\Http\Controllers\Controller;
use App\Models\Penugasan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProtokolController extends Controller
{
    /**
     * Display a listing of protocols with indexed search and monthly workload metrics.
     * Query is optimized for large scale data (bounded subqueries and composite index).
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');
        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $query = Protokol::query()
            ->select(['id', 'name', 'phone_wa', 'nip', 'status', 'notes', 'created_at'])
            ->search($search)
            ->withMonthlyMetrics($month, $year);

        if ($statusFilter === 'active') {
            $query->active();
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', ProtokolStatus::INACTIVE->value);
        }

        $protokols = $query->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Master/DaftarProtokol', [
            'protokols' => $protokols,
            'filters' => [
                'search' => $search ?? '',
                'status' => $statusFilter,
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    /**
     * Store a newly created protocol with WhatsApp validation (BUG-08).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            // BUG-08: Format nomor WhatsApp harus berupa nomor ponsel Indonesia yang valid
            'phone_wa' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9\-\s]{6,16}$/'],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('protokols', 'nip')],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'phone_wa.regex' => 'Format nomor WhatsApp tidak valid. Gunakan format nomor ponsel Indonesia (contoh: 081234567890 atau +6281234567890).',
        ]);

        Protokol::create($validated);

        return back()->with('success', 'Pegawai protokol baru berhasil ditambahkan.');
    }

    /**
     * Update the specified protocol.
     */
    public function update(Request $request, Protokol $protokol): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone_wa' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9\-\s]{6,16}$/'],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('protokols', 'nip')->ignore($protokol->id)],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'phone_wa.regex' => 'Format nomor WhatsApp tidak valid. Gunakan format nomor ponsel Indonesia (contoh: 081234567890 atau +6281234567890).',
        ]);

        $protokol->update($validated);

        return back()->with('success', "Data pegawai {$protokol->name} berhasil diperbarui.");
    }

    /**
     * Remove the specified protocol (Soft Delete with audit preservation).
     */
    public function destroy(Protokol $protokol): RedirectResponse
    {
        $name = $protokol->name;
        $protokol->delete();

        return back()->with('success', "Pegawai protokol '{$name}' berhasil dihapus (diarsipkan dengan aman).");
    }

    /**
     * Toggle protocol active/inactive status quickly.
     */
    public function toggleStatus(Protokol $protokol): RedirectResponse
    {
        $newStatus = ($protokol->status === ProtokolStatus::ACTIVE)
            ? ProtokolStatus::INACTIVE
            : ProtokolStatus::ACTIVE;

        $protokol->update(['status' => $newStatus->value]);

        return back()->with('success', "Status {$protokol->name} berhasil diubah menjadi {$newStatus->label()}.");
    }

    /**
     * Fetch individual workload statistics and recent indexed assignments.
     */
    public function stats(Request $request, Protokol $protokol): JsonResponse
    {
        $month = (int) $request->input('month', Carbon::now()->month);
        $year = (int) $request->input('year', Carbon::now()->year);

        $protokolWithMetrics = Protokol::where('id', $protokol->id)
            ->withMonthlyMetrics($month, $year)
            ->firstOrFail();

        // Optimized with covering composite index ['protokol_id', 'kegiatan_id']
        $history = Penugasan::where('protokol_id', $protokol->id)
            ->with(['kegiatan.jenisKegiatan:id,name,location_type,payment_type'])
            ->latest('assigned_at')
            ->take(6)
            ->get();

        return response()->json([
            'protokol' => $protokolWithMetrics,
            'history' => $history,
        ]);
    }
}
