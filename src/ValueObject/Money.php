<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Exception\InvalidMoneyAmount;

class Money
{
    private function __construct(private readonly int $amount)
    {
        if ($this->amount < 0) {
            throw InvalidMoneyAmount::amountLessThanZero();
        }
    }

    public static function fromInt(int $amount): self
    {
        return new self($amount);
    }

    public function isEqual(self $money): bool
    {
        return $this->amount === $money->amount;
    }
}
