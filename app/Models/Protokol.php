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
use Illuminate\Support\Facades\DB;

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

    protected $appends = [
        'status_keseimbangan',
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
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $fridaySql = "strftime('%w', kegiatan.start_datetime) = '5'";
        } elseif ($driver === 'pgsql') {
            $fridaySql = 'EXTRACT(ISODOW FROM kegiatan.start_datetime) = 5';
        } else {
            $fridaySql = 'WEEKDAY(kegiatan.start_datetime) = 4';
        }

        return $query->withCount([
            // Total tasks in month
            'penugasan as total_tugas_bulan_ini' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled');
                });
            },
            // Total Kunker tasks in month
            'penugasan as tugas_kunker' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where('is_kunker', true);
                });
            },
            // Total WFA tasks in month (Friday non-holiday or template WFA)
            'penugasan as tugas_wfa' => function (Builder $q) use ($month, $year, $fridaySql) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year, $fridaySql) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where(function (Builder $wq) use ($fridaySql) {
                            $wq->whereHas('jenisKegiatan', function (Builder $jq) {
                                $jq->where('payment_day_type', 'wfa');
                            })->orWhere(function (Builder $fq) use ($fridaySql) {
                                $fq->where('is_holiday', false)
                                    ->where('is_weekend', false)
                                    ->whereRaw($fridaySql);
                            });
                        });
                });
            },
            // Total paid tasks (paid + conditional combined for report)
            'penugasan as tugas_dibayar_total' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->whereIn('payment_type', ['paid', 'conditional']);
                        });
                });
            },
            // Definite paid tasks (paid only)
            'penugasan as tugas_paid' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'paid');
                        });
                });
            },
            // Conditional payment tasks
            'penugasan as tugas_conditional' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'conditional');
                        });
                });
            },
            // Definite paid workday tasks
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
            // Definite paid weekend/holiday tasks
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
            // Unpaid tasks (all)
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
            // Unpaid workday tasks
            'penugasan as tugas_unpaid_workday' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where('is_weekend', false)
                        ->where('is_holiday', false)
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'unpaid');
                        });
                });
            },
            // Unpaid weekend/holiday tasks
            'penugasan as tugas_unpaid_offday' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where(function (Builder $wq) {
                            $wq->where('is_weekend', true)->orWhere('is_holiday', true);
                        })
                        ->whereHas('jenisKegiatan', function (Builder $jq) {
                            $jq->where('payment_type', 'unpaid');
                        });
                });
            },
            // Total workday tasks (paid + unpaid)
            'penugasan as tugas_workday' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where('is_weekend', false)
                        ->where('is_holiday', false);
                });
            },
            // Total weekend/holiday tasks (paid + unpaid)
            'penugasan as tugas_offday' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where(function (Builder $wq) {
                            $wq->where('is_weekend', true)->orWhere('is_holiday', true);
                        });
                });
            },
            // Dalam kota trip count
            'penugasan as trip_dalam_kota' => function (Builder $q) use ($month, $year) {
                $q->whereHas('kegiatan', function (Builder $kq) use ($month, $year) {
                    $kq->whereMonth('start_datetime', $month)
                        ->whereYear('start_datetime', $year)
                        ->where('status', '!=', 'cancelled')
                        ->where('location_type', 'dalam_kota');
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

    /**
     * Get individual fairness/balance status based on monthly duty distribution.
     * Aligned with PRD Section 3.3.2.
     *
     * @return array{label: string, badge: string, class: string}
     */
    public function getStatusKeseimbanganAttribute(): array
    {
        $total = (int) ($this->total_tugas_bulan_ini ?? 0);
        $paid = (int) ($this->tugas_dibayar_total ?? (($this->tugas_dibayar_workday ?? 0) + ($this->tugas_dibayar_offday ?? 0)));
        $offday = (int) ($this->tugas_offday ?? 0);

        if ($total === 0) {
            return [
                'label' => 'Belum Bertugas (Prioritas)',
                'badge' => 'warn',
                'class' => 'bg-amber-100 text-amber-800 border-amber-200',
            ];
        }

        if ($offday === 0) {
            return [
                'label' => 'Prioritas Weekend',
                'badge' => 'info',
                'class' => 'bg-sky-100 text-sky-800 border-sky-200',
            ];
        }

        if ($paid >= 3) {
            return [
                'label' => 'Sangat Seimbang',
                'badge' => 'optimal',
                'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            ];
        }

        return [
            'label' => 'Seimbang',
            'badge' => 'neutral',
            'class' => 'bg-slate-100 text-slate-700 border-slate-200',
        ];
    }

    public function getStatusKeseimbanganTextAttribute(): string
    {
        return $this->status_keseimbangan['label'];
    }
}
