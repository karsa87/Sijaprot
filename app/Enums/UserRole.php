<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN_PROTOKOL = 'admin_protokol';
    case OPERATOR = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN_PROTOKOL => 'Admin Protokol',
            self::OPERATOR => 'Operator',
        };
    }
}
