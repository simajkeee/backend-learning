<?php

declare(strict_types=1);

namespace App\Tests\Units;

use App\Enum\OrderStatus;
use App\Exception\OrderNotFulfillableException;
use App\Exception\OrderNotPayableException;
use App\Exception\OrderNotRefundableException;
use App\Factory\OrderFactory;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testPendingOrderCantBeRefundedAndThrowsException(): void
    {
        $order = OrderFactory::new()->create();

        $this->expectException(OrderNotRefundableException::class);
        $this->expectExceptionMessage("Can't refund the order with status pending");

        $order->refund();
    }

    public function testRefundedOrderCantBePaidAgain(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $this->expectException(OrderNotPayableException::class);
        $this->expectExceptionMessage("Can't set paid status for the order with status refunded");

        $order->markPaid();
    }

    public function testRefundedOrderCantBeFulfilled(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $this->expectException(OrderNotFulfillableException::class);
        $this->expectExceptionMessage("Can't fulfill the order with status refunded");

        $order->fulfill();
    }
}
