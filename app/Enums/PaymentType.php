<?php

namespace App\Enums;

enum PaymentType: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::PAID => 'Dibayar',
            self::UNPAID => 'Tidak Dibayar',
        };
    }
}
