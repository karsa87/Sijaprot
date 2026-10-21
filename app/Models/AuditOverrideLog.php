<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditOverrideLog extends Model
{
    use HasFactory;

    protected $table = 'audit_override_logs';

    protected $fillable = [
        'penugasan_id',
        'kegiatan_id',
        'previous_protokol_id',
        'new_protokol_id',
        'overridden_by',
        'reason',
    ];

    public function penugasan(): BelongsTo
    {
        return $this->belongsTo(Penugasan::class, 'penugasan_id');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id')->withTrashed();
    }

    public function previousProtokol(): BelongsTo
    {
        return $this->belongsTo(Protokol::class, 'previous_protokol_id');
    }

    public function newProtokol(): BelongsTo
    {
        return $this->belongsTo(Protokol::class, 'new_protokol_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
