<?php

declare(strict_types=1);

namespace App\Message;

class PaymentProcessing
{
    public function __construct(
        private readonly string $providerEventId,
        private readonly int $orderId,
        private readonly int $total,
        private readonly string $currency,
        private readonly string $content,
        private readonly string $idempotencyKey,
    ) {
    }

    public function getProviderEventId(): string
    {
        return $this->providerEventId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
