<?php

declare(strict_types=1);

namespace App\Tests\Units;

use App\Entity\PaymentProviderEvent;
use App\Enum\OrderStatus;
use App\Exception\OrderNotFulfillableException;
use App\Exception\OrderNotPayableException;
use App\Exception\OrderNotRefundableException;
use App\Exception\OrderPaymentProviderEventException;
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

    public function testOrderKnowsItsPaymentProviderEventAfterEventIsCreated(): void
    {
        $order = OrderFactory::new()->create();

        new PaymentProviderEvent(
            order: $order,
            providerEventId: 'evt_123',
            payload: '{}',
        );

        $this->assertTrue($order->hasProviderEventId('evt_123'));
    }

    public function testAttachingWrongPaymentEventTriggersException(): void
    {
        $order = OrderFactory::new()->create();
        new PaymentProviderEvent(
            order: $order,
            providerEventId: 'evt_123',
            payload: '{}',
        );

        $order2 = OrderFactory::new()->create();
        $paymentProviderEvent2 = new PaymentProviderEvent(
            order: $order2,
            providerEventId: 'evt_1234',
            payload: '{}',
        );

        $this->expectException(OrderPaymentProviderEventException::class);
        $this->expectExceptionMessage("This event can't be attached to the order because it references another order entry");
        $order->attachPaymentProviderEvent($paymentProviderEvent2);
    }
}
