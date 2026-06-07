<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case PAID = 'paid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
