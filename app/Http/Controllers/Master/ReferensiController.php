<?php

namespace App\Http\Controllers\Master;

use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use App\Models\JenisKegiatan;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReferensiController extends Controller
{
    /**
     * Display master referensi: Jenis Kegiatan & Hari Libur Nasional.
     */
    public function index(Request $request): Response
    {
        $year = (int) $request->input('year', Carbon::now()->year);

        $jenisKegiatans = JenisKegiatan::query()
            ->withCount('kegiatan')
            ->orderBy('name')
            ->get();

        $hariLiburs = HariLibur::query()
            ->forYear($year)
            ->orderBy('tanggal')
            ->get();

        return Inertia::render('Master/MasterReferensi', [
            'jenisKegiatans' => $jenisKegiatans,
            'hariLiburs' => $hariLiburs,
            'selectedYear' => $year,
        ]);
    }

    /**
     * Store new Jenis Kegiatan template.
     */
    public function storeJenis(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('jenis_kegiatan', 'code')],
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
            'payment_day_type' => ['nullable', Rule::enum(PaymentDayType::class)],
            'location_type' => ['required', 'in:dalam_kota,luar_kota'],
            'is_kunker' => ['nullable', 'boolean'],
            // Batasi nilai maksimal untuk mencegah numeric overflow PostgreSQL decimal(12,2)
            'estimated_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_kunker'] = (bool) ($validated['is_kunker'] ?? false);
        if ($validated['payment_type'] === PaymentType::UNPAID->value || $validated['payment_type'] === 'unpaid') {
            $validated['payment_day_type'] = null;
        }

        JenisKegiatan::create($validated);

        return back()->with('success', 'Jenis kegiatan baru berhasil ditambahkan.');
    }

    /**
     * Update existing Jenis Kegiatan template.
     */
    public function updateJenis(Request $request, JenisKegiatan $jenisKegiatan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('jenis_kegiatan', 'code')->ignore($jenisKegiatan->id)],
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
            'payment_day_type' => ['nullable', Rule::enum(PaymentDayType::class)],
            'location_type' => ['required', 'in:dalam_kota,luar_kota'],
            'is_kunker' => ['nullable', 'boolean'],
            'estimated_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_kunker'] = (bool) ($validated['is_kunker'] ?? false);
        if ($validated['payment_type'] === PaymentType::UNPAID->value || $validated['payment_type'] === 'unpaid') {
            $validated['payment_day_type'] = null;
        }

        $jenisKegiatan->update($validated);

        return back()->with('success', 'Template jenis kegiatan berhasil diperbarui.');
    }

    /**
     * Safe delete Jenis Kegiatan template.
     * If referenced in activities: soft delete (archive) to preserve referential integrity.
     * If not referenced: hard delete permanently.
     */
    public function destroyJenis(JenisKegiatan $jenisKegiatan): RedirectResponse
    {
        if ($jenisKegiatan->kegiatan()->exists()) {
            $jenisKegiatan->delete();

            return back()->with('success', "Template '{$jenisKegiatan->name}' berhasil diarsipkan (soft delete) karena memiliki riwayat kegiatan terkait.");
        }

        $jenisKegiatan->forceDelete();

        return back()->with('success', "Template '{$jenisKegiatan->name}' berhasil dihapus permanen dari sistem.");
    }

    /**
     * Store new Hari Libur.
     */
    public function storeLibur(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date', Rule::unique('hari_libur', 'tanggal')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:nasional,cuti_bersama,hari_besar'],
        ]);

        $year = Carbon::parse($validated['tanggal'])->year;
        $validated['tahun'] = $year;

        HariLibur::create($validated);

        return back()->with('success', 'Hari libur berhasil disimpan.');
    }

    /**
     * Update existing Hari Libur.
     */
    public function updateLibur(Request $request, HariLibur $hariLibur): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date', Rule::unique('hari_libur', 'tanggal')->ignore($hariLibur->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:nasional,cuti_bersama,hari_besar'],
        ]);

        $year = Carbon::parse($validated['tanggal'])->year;
        $validated['tahun'] = $year;

        $hariLibur->update($validated);

        return back()->with('success', 'Hari libur berhasil diperbarui.');
    }

    /**
     * Delete Hari Libur.
     */
    public function destroyLibur(HariLibur $hariLibur): RedirectResponse
    {
        $hariLibur->delete();

        return back()->with('success', 'Hari libur berhasil dihapus.');
    }
}
