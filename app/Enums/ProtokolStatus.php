<?php

namespace App\Enums;

enum ProtokolStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::INACTIVE => 'Nonaktif',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::INACTIVE => 'bg-rose-100 text-rose-800 border-rose-200',
        };
    }
}
