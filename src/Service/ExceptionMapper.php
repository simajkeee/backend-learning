<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotFulfillableException;
use App\Exception\OrderNotPayableException;
use App\Exception\OrderNotRefundableException;
use App\ValueObject\HttpResponse;

class ExceptionMapper
{
    public function toHttpResponse(\Throwable $exception): ?HttpResponse
    {
        return match (get_class($exception)) {
            OrderNotFoundException::class => new HttpResponse(404, 'order_not_found'),
            OrderNotFulfillableException::class => new HttpResponse(422, 'order_not_fulfillable'),
            OrderNotPayableException::class => new HttpResponse(422, 'order_not_payable'),
            OrderNotRefundableException::class => new HttpResponse(422, 'order_not_refundable'),
        };
    }
}
