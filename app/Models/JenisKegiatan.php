<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisKegiatan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_kegiatan';

    protected $fillable = [
        'name',
        'code',
        'payment_type',
        'payment_day_type',
        'location_type',
        'is_kunker',
        'estimated_fee',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'payment_day_type' => PaymentDayType::class,
            'location_type' => LocationType::class,
            'is_kunker' => 'boolean',
            'estimated_fee' => 'decimal:2',
        ];
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'jenis_kegiatan_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_type === PaymentType::PAID;
    }

    public function isConditional(): bool
    {
        return $this->payment_type === PaymentType::CONDITIONAL;
    }

    public function isUnpaid(): bool
    {
        return $this->payment_type === PaymentType::UNPAID;
    }

    public function isKunker(): bool
    {
        return (bool) $this->is_kunker;
    }

    public function isWfa(): bool
    {
        return $this->payment_day_type === PaymentDayType::WFA;
    }

    public function isLuarKota(): bool
    {
        return $this->location_type === LocationType::LUAR_KOTA;
    }
}
