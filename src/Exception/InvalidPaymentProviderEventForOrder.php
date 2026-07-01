<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidPaymentProviderEventForOrder extends \LogicException
{
    public static function eventAlreadyBelongsToAnotherOrder(
        string $providerEventId,
        int $expectedOrder,
        int $realOrder,
    ): self {
        return new self(
            sprintf(
                'Provider event "%s" cannot be applied to order %d because it already belongs to order %d.',
                $providerEventId,
                $expectedOrder,
                $realOrder,
            ),
        );
    }

    public static function eventTotalNotEqualsOrderTotal(
        string $providerEventId,
        int $total,
        int $orderId,
    ): self {
        return new self(
            sprintf(
                'Provider event "%s" has different total amount(%d) than order snapshot %d',
                $providerEventId,
                $total,
                $orderId,
            ),
        );
    }

    public static function eventCurrencyNotEqualsOrderCurrency(
        string $providerEventId,
        string $currency,
        int $orderId,
    ): self {
        return new self(
            sprintf(
                'Provider event "%s" has different currency(%s) than order snapshot %d',
                $providerEventId,
                $currency,
                $orderId,
            ),
        );
    }
}
