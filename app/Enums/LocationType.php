<?php

namespace App\Enums;

enum LocationType: string
{
    case DALAM_KOTA = 'dalam_kota';
    case LUAR_KOTA = 'luar_kota';

    public function label(): string
    {
        return match ($this) {
            self::DALAM_KOTA => 'Dalam Kota',
            self::LUAR_KOTA => 'Luar Kota',
        };
    }
}
