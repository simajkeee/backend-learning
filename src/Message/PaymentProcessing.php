<?php

declare(strict_types=1);

namespace App\Message;

class PaymentProcessing
{
    public function __construct(private readonly string $content, private readonly string $idempotencyKey)
    {
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
