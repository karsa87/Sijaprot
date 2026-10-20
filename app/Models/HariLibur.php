<?php

namespace App\Models;

use App\Enums\LiburType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_libur';

    protected $fillable = [
        'tanggal',
        'tahun',
        'name',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tahun' => 'integer',
            'type' => LiburType::class,
        ];
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('tahun', $year);
    }

    public static function isHoliday(Carbon|string $date): bool
    {
        $d = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();
        return static::where('tanggal', $d)->exists();
    }
}
