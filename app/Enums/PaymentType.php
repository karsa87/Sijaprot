<?php

namespace App\Enums;

enum PaymentType: string
{
    case PAID = 'paid';
    case CONDITIONAL = 'conditional';
    case UNPAID = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::PAID => 'Dibayar',
            self::CONDITIONAL => 'Kemungkinan Dibayar',
            self::UNPAID => 'Tidak Dibayar',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isConditional(): bool
    {
        return $this === self::CONDITIONAL;
    }

    public function isUnpaid(): bool
    {
        return $this === self::UNPAID;
    }
}
