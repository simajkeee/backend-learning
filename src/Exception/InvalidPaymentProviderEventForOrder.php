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
}
