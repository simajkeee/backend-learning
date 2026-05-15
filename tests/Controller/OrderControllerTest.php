<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    public function testProductOrderCreatedAndRedirected(): void
    {
        self::createClient()
            ->request('POST', '/products/product-0/orders');

        $orderRepository = static::getContainer()->get(OrderRepository::class);
        $orders = $orderRepository->findBy([]);

        $this->assertCount(1, $orders);
        $this->assertResponseRedirects("/orders/{$orders[0]->getId()}");
    }

    public function testRedirectShowsProductNameAndStatus(): void
    {
        $client = self::createClient();
        $client->followRedirects();
        $client->request('POST', '/products/product-0/orders');

        $orderRepository = static::getContainer()->get(OrderRepository::class);
        $orders = $orderRepository->findBy([]);
        $this->assertCount(1, $orders);

        $order = $orders[0];
        $product = $order->getProduct();

        $this->assertSelectorTextContains('p', $product->getName());
        $this->assertSelectorTextContains('span', $order->getStatus());
    }

    public function testFakeSlugNotFoundOnOrderCreation(): void
    {
        self::createClient()
            ->request('POST', '/products/fake---0121412412/orders');

        $this->assertResponseStatusCodeSame(404);
    }
}
