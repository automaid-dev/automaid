<?php

namespace App\Enums;

enum UserTypeEnum: string
{
    case TYPE_GIG_WORKER = '1';
    case TYPE_STAFF_AUTOMAID = '2';
    case TYPE_OUTLET_PARTNER = '3';
    case TYPE_AUTOMAID_OUTLET = '4';

    public function label(): string
    {
        return match ($this) {
            self::TYPE_GIG_WORKER => 'GIG WORKER',
            self::TYPE_STAFF_AUTOMAID => 'STAFF AUTO MAID',
            self::TYPE_OUTLET_PARTNER => 'OUTLET PARTNER',
            self::TYPE_AUTOMAID_OUTLET => 'AUTO MAID OUTLET',
        };
    }

    public static function getByCategory(UserRoleEnum $role): array
    {
        return match ($role) {
            UserRoleEnum::RIDER => [
                self::TYPE_GIG_WORKER->value => self::TYPE_GIG_WORKER->label(),
                self::TYPE_STAFF_AUTOMAID->value => self::TYPE_STAFF_AUTOMAID->label(),
            ],
            UserRoleEnum::MERCHANT => [
                self::TYPE_OUTLET_PARTNER->value => self::TYPE_OUTLET_PARTNER->label(),
                self::TYPE_AUTOMAID_OUTLET->value => self::TYPE_AUTOMAID_OUTLET->label(),
            ],
        };
    }
}
