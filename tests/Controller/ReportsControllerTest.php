<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReportsControllerTest extends WebTestCase
{
    public function setUp(): void
    {
        self::createClient();
    }

    public function testReportShowsFulfilledOrder(): void
    {
        $orderFulfilled = OrderFactory::createWithStatus(OrderStatus::FULFILLED);
        $orderPaid = OrderFactory::createWithStatus(OrderStatus::PAID);
        $orderRefunded = OrderFactory::createWithStatus(OrderStatus::REFUNDED);
        $orderPending = OrderFactory::createWithStatus(OrderStatus::PENDING);

        $client = self::getClient();
        $client->request('GET', '/reports/orders/fulfilled');

        $this->assertResponseIsSuccessful();

        $pattern = "[data-testid='order-%d-status']";
        $this->assertSelectorTextContains(sprintf($pattern, $orderFulfilled->getId()), 'Status: "fulfilled"');
        $this->assertSelectorNotExists(sprintf($pattern, $orderPaid->getId()));
        $this->assertSelectorNotExists(sprintf($pattern, $orderRefunded->getId()));
        $this->assertSelectorNotExists(sprintf($pattern, $orderPending->getId()));
    }

    public function testReportDoesntShowPendingOrder(): void
    {
        OrderFactory::createWithStatus(OrderStatus::PENDING);

        $client = self::getClient();
        $client->request('GET', '/reports/orders/fulfilled');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'No report details found');
    }

    public function testReportDoesntShowPaidOrder(): void
    {
        OrderFactory::createWithStatus(OrderStatus::PAID);

        $client = self::getClient();
        $client->request('GET', '/reports/orders/fulfilled');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'No report details found');
    }

    public function testReportDoesntShowRefundedOrder(): void
    {
        OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $client = self::getClient();
        $client->request('GET', '/reports/orders/fulfilled');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'No report details found');
    }

    public function testEmptyReportRendersSuccessfully(): void
    {
        $client = self::getClient();
        $client->request('GET', '/reports/orders/fulfilled');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'No report details found');
    }


    public function testReportOrdersSortedCorrectly(): void
    {
        $counter = 0;
        $orders = OrderFactory::new()
                    ->withStatus(OrderStatus::FULFILLED)
                    ->afterInstantiate(function (Order $order) use (&$counter): void {
                        $order->getOrderFulfillment()->setCreatedAt(
                            new DateTimeImmutable(sprintf('2026-06-01 12:%02d:00', $counter))
                        );

                        $counter++;
                    })
                    ->many(10)
                    ->create();


        $client = self::getClient();
        $crawler = $client->request('GET', '/reports/orders/fulfilled');

        $this->assertResponseIsSuccessful();

        $pageOrderIds = $crawler
            ->filter("[data-testid='order-id']")
            ->each(static fn ($node) => (int) $node->attr('data-order-id'));

        usort($orders, static function (Order $a, Order $b) {
            $dateCompare = $b->getOrderFulfillment()->getCreatedAt()
                <=> $a->getOrderFulfillment()->getCreatedAt();
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return $b->getId() <=> $a->getId();
        });

        $this->assertSame(array_map(static fn ($o) => $o->getId(), $orders), $pageOrderIds);
    }
}
