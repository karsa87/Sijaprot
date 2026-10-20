<?php

namespace App\Enums;

enum LiburType: string
{
    case NASIONAL = 'nasional';
    case CUTI_BERSAMA = 'cuti_bersama';
    case HARI_BESAR = 'hari_besar';

    public function label(): string
    {
        return match ($this) {
            self::NASIONAL => 'Libur Nasional',
            self::CUTI_BERSAMA => 'Cuti Bersama',
            self::HARI_BESAR => 'Hari Besar / Peringatan',
        };
    }
}
