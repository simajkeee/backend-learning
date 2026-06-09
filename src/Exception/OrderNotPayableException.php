<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\OrderStatus;

class OrderNotPayableException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function withDefaultMsg(OrderStatus $status, int $code = 0, ?Throwable $previous = null): self
    {
        return new self("Can't set paid status for the order with status {$status->value}", $code, $previous);
    }
}
