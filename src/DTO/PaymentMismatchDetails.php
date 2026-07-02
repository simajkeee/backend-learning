<?php

declare(strict_types=1);

namespace App\DTO;

class PaymentMismatchDetails
{
    public function __construct(
        public string $providerEventId,
        public int $orderId,
        public int $expectedAmountMinor,
        public string $expectedCurrency,
        public int $receivedAmountMinor,
        public string $receivedCurrency,
        public string $reason,
    ) {
    }
}
