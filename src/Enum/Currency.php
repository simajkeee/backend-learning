<?php

namespace App\Enum;

enum Currency: string
{
    case USD = 'USD';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
