<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\OrderStatus;

class OrderNotRefundableException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function withDefaultMsg(OrderStatus $status, int $code = 0, ?Throwable $previous = null): self
    {
        return new self("Can't refund the order with status {$status->value}", $code, $previous);
    }
}
