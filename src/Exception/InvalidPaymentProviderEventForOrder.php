<?php

declare(strict_types=1);

namespace App\Exception;

class InvalidPaymentProviderEventForOrder extends \LogicException
{
    private const string INVALID_TOTAL = 'invalid_total';

    private const string INVALID_CURRENCY = 'invalid_currency';

    private const string INVALID_ORDER = 'invalid_order';

    private string $errorCode;

    public function __construct(string $errorCode, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function eventAlreadyBelongsToAnotherOrder(
        string $providerEventId,
        int $expectedOrder,
        int $realOrder,
    ): self {
        return new self(
            self::INVALID_ORDER,
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
            self::INVALID_TOTAL,
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
            self::INVALID_CURRENCY,
            sprintf(
                'Provider event "%s" has different currency(%s) than order snapshot %d',
                $providerEventId,
                $currency,
                $orderId,
            ),
        );
    }
}
