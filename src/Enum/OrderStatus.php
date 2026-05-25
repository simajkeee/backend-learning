<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FULFILLED = 'fulfilled';
    case REFUNDED = 'refunded';
}
