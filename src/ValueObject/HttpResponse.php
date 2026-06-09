<?php

declare(strict_types=1);

namespace App\ValueObject;

readonly class HttpResponse
{
    public function __construct(private int $code, private string $error)
    {
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getError(): string
    {
        return $this->error;
    }
}
