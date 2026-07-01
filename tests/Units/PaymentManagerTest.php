<?php

declare(strict_types=1);

namespace Units;

use App\Entity\Order;
use App\Entity\Product;
use App\Exception\InvalidPaymentProviderEventForOrder;
use App\Repository\OrderRepository;
use App\Repository\PaymentProviderEventRepository;
use App\Service\PaymentManager;
use App\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentManagerTest extends TestCase
{
    private MockObject $em;

    private MockObject $orderRepo;

    private MockObject $paymentProviderEventRepo;

    private PaymentManager $paymentManager;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->em
            ->method('wrapInTransaction')
            ->willReturnCallback(function (callable $callback) {
                return $callback($this->em);
            });

        $this->orderRepo = $this->createMock(OrderRepository::class);
        $this->paymentProviderEventRepo = $this->createMock(PaymentProviderEventRepository::class);

        $this->paymentManager = new PaymentManager(
            $this->em,
            $this->orderRepo,
            $this->paymentProviderEventRepo,
        );
    }

    public function testMismatchedTotalThrowsExceptionAndDoesntChangeOrderStatus(): void
    {
        $providerEventId = 'evt_123';
        $orderId = 123;
        $total = 10444;
        $currency = 'USD';

        $order = new Order(new Product('test', 10555));

        $this->orderRepo
            ->expects($this->once())
            ->method('findAndLockById')
            ->with($orderId)
            ->willReturn($order);

        $this->paymentProviderEventRepo
            ->expects($this->never())
            ->method('findOneByProviderEventId');

        $this->em
            ->expects($this->never())
            ->method('persist');

        $this->expectException(InvalidPaymentProviderEventForOrder::class);
        $this->expectExceptionMessage(sprintf(
            'Provider event "%s" has different total amount(%d) than order snapshot %d',
            $providerEventId,
            $total,
            $orderId,
        ));
        $this->paymentManager
            ->processPaid($providerEventId, $orderId, $total, $currency, '{}');
        $this->assertFalse($order->isPaid());
    }

    public function testMismatchedCurrencyThrowsExceptionAndDoesntChangeOrderStatus(): void
    {
        $providerEventId = 'evt_123';
        $orderId = 123;
        $total = 10555;
        $currency = 'EUR';

        $order = new Order(new Product('test', $total));

        $this->orderRepo
            ->expects($this->once())
            ->method('findAndLockById')
            ->with($orderId)
            ->willReturn($order);

        $this->paymentProviderEventRepo
            ->expects($this->never())
            ->method('findOneByProviderEventId');

        $this->em
            ->expects($this->never())
            ->method('persist');

        $this->expectException(InvalidPaymentProviderEventForOrder::class);
        $this->expectExceptionMessage(sprintf(
            'Provider event "%s" has different currency(%s) than order snapshot %d',
            $providerEventId,
            $currency,
            $orderId,
        ));
        $this->paymentManager
            ->processPaid($providerEventId, $orderId, $total, $currency, '{}');
        $this->assertFalse($order->isPaid());
    }

    public function testMatchedCurrencyAndTotalChangeOrderStatusAndTriggerPersistCall(): void
    {
        $providerEventId = 'evt_123';
        $orderId = 123;
        $total = 10555;
        $currency = 'USD';

        $order = new Order(new Product('test', $total));

        $this->orderRepo
            ->expects($this->once())
            ->method('findAndLockById')
            ->with($orderId)
            ->willReturn($order);

        $this->paymentProviderEventRepo
            ->expects($this->once())
            ->method('findOneByProviderEventId');

        $this->em
            ->expects($this->once())
            ->method('persist');

        $this->paymentManager
            ->processPaid($providerEventId, $orderId, $total, $currency, '{}');
        $this->assertTrue($order->isPaid());
    }
}
