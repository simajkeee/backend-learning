<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidOrderTotal extends \LogicException implements ErrorCodeInterface
{
    public static function forOrder(string $providerEventId, int $total, int $orderId): self
    {
        return new self(
            sprintf(
                'Provider event "%s" has different total amount(%d) than order snapshot %d',
                $providerEventId,
                $total,
                $orderId,
            ),
        );
    }

    public function getErrorCode(): string
    {
        return 'invalid_total';
    }
}
