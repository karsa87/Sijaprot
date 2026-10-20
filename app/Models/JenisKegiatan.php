<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Enums\PaymentDayType;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKegiatan extends Model
{
    use HasFactory;

    protected $table = 'jenis_kegiatan';

    protected $fillable = [
        'name',
        'code',
        'payment_type',
        'payment_day_type',
        'location_type',
        'estimated_fee',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'payment_day_type' => PaymentDayType::class,
            'location_type' => LocationType::class,
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

    public function isLuarKota(): bool
    {
        return $this->location_type === LocationType::LUAR_KOTA;
    }
}
