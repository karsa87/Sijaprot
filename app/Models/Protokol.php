<?php

namespace App\Models;

use App\Enums\ProtokolStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Protokol extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'protokols';

    protected $fillable = [
        'nip',
        'name',
        'phone_wa',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProtokolStatus::class,
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class, 'protokol_id');
    }

    public function latestPenugasan(): HasOne
    {
        return $this->hasOne(Penugasan::class, 'protokol_id')->latestOfMany('assigned_at');
    }

    public function kegiatan(): BelongsToMany
    {
        return $this->belongsToMany(Kegiatan::class, 'penugasan', 'protokol_id', 'kegiatan_id')
            ->withPivot(['is_system_assigned', 'is_override', 'override_reason', 'score_snapshot', 'selection_reason'])
            ->withTimestamps();
    }

    // ==========================================
    // REUSABLE QUERY SCOPES (Optimized for 200M Rows)
    // ==========================================

    /**
     * Scope for active protocols eligible for scheduling pool.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProtokolStatus::ACTIVE->value);
    }

    /**
     * Fast search index query by name or WhatsApp number.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->where(function (Builder $sub) use ($term, $operator) {
            $sub->where('name', $operator, "%{$term}%")
                ->orWhere('phone_wa', 'LIKE', "%{$term}%")
                ->orWhere('nip', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Efficiently attach aggregated task metrics for a specific month
     * using indexed subqueries without loading full relation collections into memory.
     */
    public function scopeWithMonthlyMetrics(Builder $query, int $month, int $year): Builder
    {
        return $query->withCount([
            // Total tasks in month
            'penugasan as total_tugas_bulan_ini' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled');
                });
            },
            // Paid workday tasks
            'penugasan as tugas_dibayar_workday' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where('is_weekend', false)
                        ->where('is_holiday', false)
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'paid');
                        });
                });
            },
            // Paid weekend/holiday tasks
            'penugasan as tugas_dibayar_offday' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where(function (Builder $wq) {
                            $wq->where('is_weekend', true)->orWhere('is_holiday', true);
                        })
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'paid');
                        });
                });
            },
            // Unpaid tasks
            'penugasan as tugas_unpaid' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'unpaid');
                        });
                });
            },
            // Luar kota trip count
            'penugasan as trip_luar_kota' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where('location_type', 'luar_kota');
                });
            },
        ]);
    }
}
