<?php

declare(strict_types=1);

namespace App\Tests\Units;

use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testPendingOrderCantBeRefundedAndThrowsException(): void
    {
        $order = OrderFactory::new()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Can't refund the order with status pending");

        $order->refund();
    }

    public function testRefundedOrderCantBePaidAgain(): void
    {
        $order = OrderFactory::withStatus(OrderStatus::REFUNDED);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Can't set paid status for the order with status refunded");

        $order->markPaid();
    }

    public function testRefundedOrderCantBeFulfilled(): void
    {
        $order = OrderFactory::withStatus(OrderStatus::REFUNDED);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Can't fulfill the order with status refunded");

        $order->fulfill();
    }
}
