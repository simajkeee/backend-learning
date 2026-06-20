<?php

declare(strict_types=1);

namespace App\Exception;

class OrderPaymentProviderEventException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function withDefaultMsg(int $code = 0, ?\Throwable $previous = null): self
    {
        return new self("This event can't be attached to the order because it references another order entry");
    }
}
