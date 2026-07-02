<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidOrderCurrency extends \LogicException implements ErrorCodeInterface
{
    public static function forOrder(string $providerEventId, string $currency, int $orderId): self
    {
        return new self(
            sprintf(
                'Provider event "%s" has different currency(%s) than order snapshot %d',
                $providerEventId,
                $currency,
                $orderId,
            ),
        );
    }

    public function getErrorCode(): string
    {
        return 'invalid_currency';
    }
}
