<?php

declare(strict_types=1);

namespace App\Enum;

enum Currency: string
{
    case USD = 'USD';

    case EUR = 'EUR';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
