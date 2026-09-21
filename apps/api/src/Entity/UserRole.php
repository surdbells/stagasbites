<?php

declare(strict_types=1);

namespace StagasBites\Entity;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::CUSTOMER => 'Customer',
        };
    }
}
