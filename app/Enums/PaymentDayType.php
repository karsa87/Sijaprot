<?php

namespace App\Enums;

enum PaymentDayType: string
{
    case WORKDAY = 'workday';
    case WFA = 'wfa';
    case OFFDAY = 'offday';

    public function label(): string
    {
        return match ($this) {
            self::WORKDAY => 'Hari Kerja (Senin–Kamis)',
            self::WFA => 'Jum\'at (WFA)',
            self::OFFDAY => 'Luar Hari Kerja (Sabtu/Minggu/Libur)',
        };
    }

    public function isWorkday(): bool
    {
        return $this === self::WORKDAY;
    }

    public function isWfa(): bool
    {
        return $this === self::WFA;
    }

    public function isOffday(): bool
    {
        return $this === self::OFFDAY;
    }
}
