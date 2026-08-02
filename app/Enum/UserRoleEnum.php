<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case ROLE_RIDER = 'rider';
    case ROLE_MERCHANT = 'merchant';

    public function label(): string
    {
        return match ($this) {
            self::ROLE_RIDER => 'RIDER',
            self::ROLE_MERCHANT => 'MERCHANT',
        };
    }
}
