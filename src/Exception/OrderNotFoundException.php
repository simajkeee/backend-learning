<?php

declare(strict_types=1);

namespace App\Exception;

class OrderNotFoundException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function withDefaultMsg(int $orderId, int $code = 0, ?Throwable $previous = null): self
    {
        return new self("Order {$orderId} not found");
    }
}
