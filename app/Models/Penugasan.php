<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penugasan extends Model
{
    use HasFactory;

    protected $table = 'penugasan';

    protected $fillable = [
        'kegiatan_id',
        'protokol_id',
        'assigned_at',
        'assigned_by',
        'is_system_assigned',
        'is_override',
        'override_reason',
        'score_snapshot',
        'selection_reason',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'is_system_assigned' => 'boolean',
            'is_override' => 'boolean',
            'score_snapshot' => 'decimal:2',
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }

    public function protokol(): BelongsTo
    {
        return $this->belongsTo(Protokol::class, 'protokol_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function overrideLogs(): HasMany
    {
        return $this->hasMany(AuditOverrideLog::class, 'penugasan_id');
    }

    // ==========================================
    // REUSABLE QUERY SCOPES
    // ==========================================

    public function scopeOverridden(Builder $query): Builder
    {
        return $query->where('is_override', true);
    }

    public function scopeForProtokol(Builder $query, int $protokolId): Builder
    {
        return $query->where('protokol_id', $protokolId);
    }
}
