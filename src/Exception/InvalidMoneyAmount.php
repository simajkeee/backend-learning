<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidMoneyAmount extends \LogicException
{
    public static function amountLessThanZero(): self
    {
        return new self("Money amount can't be less than zero.");
    }
}
