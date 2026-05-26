<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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
        $client = self::getClient();
        $crawler = $client->request('GET', '/products/product-0');
        $form = $crawler->selectButton('Buy')->form();
        $client->submit($form);

        $orders = $this->orderRepo->findAll();

        $this->assertCount(1, $orders);
        $this->assertResponseRedirects("/orders/{$orders[0]->getId()}");
    }

    public function testRedirectShowsProductNameAndStatus(): void
    {
        $client = self::getClient();
        $client->followRedirects();

        $crawler = $client->request('GET', '/products/product-0');
        $form = $crawler->selectButton('Buy')->form();
        $client->submit($form);

        $orders = $this->orderRepo->findAll();
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
        $order = OrderFactory::new()->create();
        static::getClient()
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('button', 'Mark as paid');
    }

    public function testOrderChangesStatusAndRedirects(): void
    {
        $client = self::getClient();

        $order = OrderFactory::new()->create();
        $id = $order->getId();

        $crawler = $client->request('GET', "/orders/{$id}");
        $form = $crawler->selectButton('Mark as paid')->form();
        $client->submit($form);

        $this->assertResponseRedirects("/orders/{$id}");

        $order = $this->orderRepo->findOneBy(['id' => $id]);
        $this->assertSame(OrderStatus::PAID, $order->getStatus());
    }

    public function testFollowRedirectShowsPaidStatus(): void
    {
        $client = self::getClient();
        $client->followRedirects();

        $order = OrderFactory::new()->create();
        $id = $order->getId();

        $crawler = $client->request('GET', "/orders/{$id}");
        $form = $crawler->selectButton('Mark as paid')->form();
        $client->submit($form);

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
        $order = OrderFactory::withStatus(OrderStatus::PAID);

        self::getClient()
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('button', 'Fulfill');
    }

    public function testPendingOrderPageDoesntShowFulfillAction(): void
    {
        $order = OrderFactory::new()->create();

        self::getClient()
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextNotContains('button', 'Fulfill');
    }

    public function testOrderIsFulfilledAndRedirected(): void
    {
        $order = OrderFactory::withStatus(OrderStatus::PAID);
        $id = $order->getId();
        $this->assertSame(OrderStatus::PAID, $order->getStatus());

        $client = self::getClient();
        $client->followRedirects();

        $crawler = $client->request('GET', "/orders/{$id}");
        $form = $crawler->selectButton('Fulfill')->form();
        $client->submit($form);

        $order = $this->orderRepo->findOneBy(['id' => $id]);

        $this->assertSame(OrderStatus::FULFILLED, $order->getStatus());
        $this->assertSelectorTextSame('span', 'Status: fulfilled');
    }

    public function testLogicExceptionIsThrownPendingToFulfilledStatusChange(): void
    {
        $order = OrderFactory::new()->create();

        $this->expectException(LogicException::class);

        $order->fulfill();
    }

    public function testFulfillEndpointReturns500WhenOrderPending(): void
    {
        $order = OrderFactory::new()->create();

        $client = self::getClient();

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        $client->request('POST', "/orders/{$order->getId()}/fulfill", [
            'token' => $tokenValue,
        ]);
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

    public function testRefundableOrderPageShowsRefundAction(): void
    {
        $order = OrderFactory::withStatus(OrderStatus::PAID);

        self::getClient()->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('button', 'Refund');
    }

    public function testNonRefundableOrderPageDoesntShowRefundAction(): void
    {
        $order = OrderFactory::new()->create();

        self::getClient()->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextNotContains('button', 'Refund');
    }

    public function testOrderStatusIsChangedToRefundedAndRedirects(): void
    {
        $order = OrderFactory::withStatus(OrderStatus::PAID);

        $client = self::getClient();

        $crawler = $client->request('GET', "/orders/{$order->getId()}");
        $form = $crawler->selectButton('Refund')->form();
        $client->submit($form);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::REFUNDED, $order->getStatus());

        $this->assertResponseRedirects("/orders/{$order->getId()}");
    }

    public function testAfterRefundedActionShowsRefundedStatus(): void
    {
        $order = OrderFactory::withStatus(OrderStatus::PAID);

        $client = self::getClient();
        $client->followRedirects();

        $crawler = $client->request('GET', "/orders/{$order->getId()}");
        $form = $crawler->selectButton('Refund')->form();
        $client->submit($form);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::REFUNDED, $order->getStatus());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('span', 'Status: refunded');
    }

    public function testInvalidOrderRefundReturns404(): void
    {
        self::getClient()
            ->request('POST', '/orders/-9999/refund');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testMissingCsrfDoesntChangeOrderState(): void
    {
        $order = OrderFactory::new()->create();

        self::getClient()
            ->request('POST', "/orders/{$order->getId()}/pay");

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
    }

    public function testOrderToPaidWorksWithCsrf(): void
    {
        $order = OrderFactory::new()->create();

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        self::getClient()
            ->request('POST', "/orders/{$order->getId()}/pay", ['token' => $tokenValue]);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::PAID, $order->getStatus());
    }

    public function testOrderToFulfillWorksWithCsrf(): void
    {
        $order = OrderFactory::new()->paid()->create();

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        self::getClient()
            ->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::FULFILLED, $order->getStatus());
    }

    public function testOrderToRefundWorksWithCsrf(): void
    {
        $order = OrderFactory::new()->paid()->create();

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        self::getClient()
            ->request('POST', "/orders/{$order->getId()}/refund", ['token' => $tokenValue]);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::REFUNDED, $order->getStatus());
    }

    private function setCsrfManagerWithToken(string $tokenValue): void
    {
        $csrfManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfManager
            ->method('isTokenValid')
            ->willReturn(true);
        $csrfManager
            ->method('getToken')
            ->willReturnCallback(
                fn (string $tokenId) => new CsrfToken($tokenId, $tokenValue)
            );
        self::getContainer()->set(CsrfTokenManagerInterface::class, $csrfManager);
    }
}
