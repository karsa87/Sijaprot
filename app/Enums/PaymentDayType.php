<?php

namespace App\Enums;

enum PaymentDayType: string
{
    case WORKDAY = 'workday';
    case OFFDAY = 'offday';

    public function label(): string
    {
        return match ($this) {
            self::WORKDAY => 'Hari Kerja (Senin–Jumat)',
            self::OFFDAY => 'Luar Hari Kerja (Sabtu/Minggu/Libur)',
        };
    }
}
