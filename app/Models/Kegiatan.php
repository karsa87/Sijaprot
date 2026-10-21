<?php

namespace App\Models;

use App\Enums\KegiatanStatus;
use App\Enums\LocationType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kegiatan';

    protected static function booted(): void
    {
        static::deleting(function (Kegiatan $kegiatan) {
            // Bersihkan penugasan terkait saat kegiatan dihapus agar jadwal personil terbebas
            $kegiatan->penugasan()->delete();
        });
    }

    protected $fillable = [
        'jenis_kegiatan_id',
        'title',
        'detail',
        'start_datetime',
        'end_datetime',
        'jumlah_protokol',
        'is_holiday',
        'is_weekend',
        'location_type',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'jumlah_protokol' => 'integer',
            'is_holiday' => 'boolean',
            'is_weekend' => 'boolean',
            'location_type' => LocationType::class,
            'status' => KegiatanStatus::class,
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function jenisKegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class, 'jenis_kegiatan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class, 'kegiatan_id');
    }

    public function protokols(): BelongsToMany
    {
        return $this->belongsToMany(Protokol::class, 'penugasan', 'kegiatan_id', 'protokol_id')
            ->withPivot(['is_system_assigned', 'is_override', 'override_reason', 'score_snapshot', 'selection_reason'])
            ->withTimestamps();
    }

    public function auditOverrides(): HasMany
    {
        return $this->hasMany(AuditOverrideLog::class, 'kegiatan_id');
    }

    // ==========================================
    // REUSABLE QUERY SCOPES (Optimized for 200M Rows)
    // ==========================================

    /**
     * Efficient range-bounded calendar query that leverages composite index
     * idx_kegiatan_range_status (start_datetime, end_datetime, status).
     */
    public function scopeInMonth(Builder $query, int $month, int $year): Builder
    {
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();

        return $query->whereBetween('start_datetime', [$startOfMonth, $endOfMonth]);
    }

    /**
     * Index-friendly overlap checker: StartA < EndB AND EndA > StartB
     */
    public function scopeOverlappingWith(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $s = is_string($start) ? Carbon::parse($start) : $start;
        $e = is_string($end) ? Carbon::parse($end) : $end;

        return $query->where('start_datetime', '<', $e)
            ->where('end_datetime', '>', $s);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', KegiatanStatus::SCHEDULED->value);
    }

    public function isLuarKota(): bool
    {
        return $this->location_type === LocationType::LUAR_KOTA;
    }

    public function isPaid(): bool
    {
        return $this->jenisKegiatan?->isPaid() ?? false;
    }
}
