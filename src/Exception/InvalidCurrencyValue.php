<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidCurrencyValue extends \LogicException
{
    public static function unsupportedValueProvided(string $value): self
    {
        return new self(sprintf('Unsupported value provided %s', $value));
    }
}
