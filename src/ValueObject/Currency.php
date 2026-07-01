<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\Currency as CurrencyEnum;
use App\Exception\InvalidCurrencyValue;

class Currency
{
    private function __construct(private readonly CurrencyEnum $currency)
    {
    }

    public static function fromEnum(CurrencyEnum $currency): self
    {
        return new self($currency);
    }

    public static function fromString(string $currency): self
    {
        $currencyEnum = CurrencyEnum::tryFrom($currency);

        if (!$currencyEnum) {
            throw InvalidCurrencyValue::unsupportedValueProvided($currency);
        }

        return new self($currencyEnum);
    }

    public function isSame(self $currency): bool
    {
        return $this->currency === $currency->currency;
    }
}
