<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        $this->assertSelectorTextContains('span', $order->getStatus());
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
        $this->assertSame('paid', $order->getStatus());
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

    public function testPayingAlreadyPaidOrderIsNoOpAndRedirects(): void
    {
        $client = self::getClient();

        $order = $this->createOrder();
        $id = $order->getId();

        $client->request('POST', "/orders/{$id}/pay");
        $this->assertResponseRedirects("/orders/{$id}");

        $client->request('POST', "/orders/{$id}/pay");
        $this->assertResponseRedirects("/orders/{$id}");

        $reloadedOrder = $this->orderRepo->find($id);

        $this->assertSame('paid', $reloadedOrder->getStatus());
    }

    private function createOrder(): Order
    {
        $order = new Order();
        $product = new Product();
        $product->setName('test');
        $product->setPrice('10.00');
        $order->setProduct($product);
        $this->em->persist($product);
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
