<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    private OrderRepository $orderRepo;

    private EntityManagerInterface $em;

    public function setUp(): void
    {
        self::createClient();
        $container = static::getContainer();
        $this->orderRepo = $container->get(OrderRepository::class);
        $this->em = $container->get(EntityManagerInterface::class);
    }

    public function testProductOrderCreatedAndRedirected(): void
    {
        self::getClient()
            ->request('POST', '/products/product-0/orders');

        $orders = $this->orderRepo->findBy([]);

        $this->assertCount(1, $orders);
        $this->assertResponseRedirects("/orders/{$orders[0]->getId()}");
    }

    public function testRedirectShowsProductNameAndStatus(): void
    {
        $client = self::getClient();
        $client->followRedirects();
        $client->request('POST', '/products/product-0/orders');

        $orders = $this->orderRepo->findBy([]);
        $this->assertCount(1, $orders);

        $order = $orders[0];
        $product = $order->getProduct();

        $this->assertSelectorTextContains('p', $product->getName());
        $this->assertSelectorTextContains('span', $order->getStatus()->value);
    }

    public function testFakeSlugNotFoundOnOrderCreation(): void
    {
        self::getClient()
            ->request('POST', '/products/fake---0121412412/orders');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPendingOrderPageShowsMarkAsPaidAction(): void
    {
        static::getClient()
            ->request('GET', "/orders/{$this->createOrder()->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('button', 'Mark as paid');
    }

    public function testOrderChangesStatusAndRedirects(): void
    {
        $client = self::getClient();

        $order = $this->createOrder();
        $id = $order->getId();

        $client->request('POST', "/orders/{$id}/pay");

        $this->assertResponseRedirects("/orders/{$id}");

        $order = $this->orderRepo->findOneBy(['id' => $id]);
        $this->assertSame(OrderStatus::PAID, $order->getStatus());
    }

    public function testFollowRedirectShowsPaidStatus(): void
    {
        $client = self::getClient();
        $order = $this->createOrder();
        $id = $order->getId();

        $client->followRedirects();
        $client->request('POST', "/orders/{$id}/pay");

        $this->assertSelectorTextSame('span', 'Status: paid');
    }

    public function testInvalidOrderIdReturns404(): void
    {
        $client = self::getClient();
        $client->request('POST', "/orders/-999/pay");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPaidOrderPageShowsFulfillAction(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::PAID);

        self::getClient()
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('button', 'Fulfill');
    }

    public function testPendingOrderPageDoesntShowFulfillAction(): void
    {
        $order = $this->createOrder();

        self::getClient()
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextNotContains('button', 'Fulfill');
    }

    public function testOrderIsFulfilledAndRedirected(): void
    {
        $order = $this->createOrderWithStatus(OrderStatus::PAID);
        $id = $order->getId();
        $this->assertSame(OrderStatus::PAID, $order->getStatus());

        $client = self::getClient();
        $client->followRedirects();
        $client->request('POST', "/orders/{$id}/fulfill");

        $order = $this->orderRepo->findOneBy(['id' => $id]);

        $this->assertSame(OrderStatus::FULFILLED, $order->getStatus());
        $this->assertSelectorTextSame('span', 'Status: fulfilled');
    }

    public function testLogicExceptionIsThrownPendingToFulfilledStatusChange(): void
    {
        $order = $this->createOrder();

        $this->expectException(LogicException::class);

        $order->fulfill();
    }

    public function testFulfillEndpointReturns500WhenOrderPending(): void
    {
        $order = $this->createOrder();

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/fulfill");
        $response = $client->getResponse();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString("Can't fulfill the order with status pending", $response->getContent());
    }

    public function testFulfillEndpointReturns404WithNotExistingOrder(): void
    {
        self::getClient()
            ->request('POST', '/orders/-99999999/fulfill');

        $this->assertResponseStatusCodeSame(404);
    }

    private function createOrder(): Order
    {
        $product = new Product();
        $product->setName('test');
        $product->setPrice('10.00');
        $order = new Order($product);
        $this->em->persist($product);
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    private function createOrderWithStatus(OrderStatus $orderStatus): Order
    {
        $product = new Product();
        $product->setName('test');
        $product->setPrice('10.00');
        $order = new Order($product);

        if ($orderStatus !== OrderStatus::PENDING) {
            $order->markPaid();
        }

        if ($orderStatus === OrderStatus::FULFILLED) {
            $order->fulfill();
        } else if ($orderStatus === OrderStatus::REFUNDED) {
            $order->refund();
        }

        $this->em->persist($product);
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
