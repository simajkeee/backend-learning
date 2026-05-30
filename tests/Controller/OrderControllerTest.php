<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\OrderFulfillment;
use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use App\Factory\OrderFulfillmentFactory;
use App\Repository\OrderFulfillmentRepository;
use App\Repository\OrderRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

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
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);
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
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

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
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

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
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

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

    public function testMissingCsrfDoesntChangePendingOrderStatusToPaid(): void
    {
        $order = OrderFactory::new()->create();

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/pay");

        $order = $this->orderRepo->find($order->getId());

        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $response = $client->getResponse();
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
    }

    public function testMissingCsrfDoesntChangePaidOrderStatusToFulfilled(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/fulfill");

        $order = $this->orderRepo->find($order->getId());

        $this->assertSame(OrderStatus::PAID, $order->getStatus());
        $response = $client->getResponse();
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
    }

    public function testPendingOrderCantBeRefunded(): void
    {
        $order = OrderFactory::new()->create();

        $value = 'test-token';
        $this->setCsrfManagerWithToken($value);

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/refund", ['token' => $value]);

        $this->assertResponseStatusCodeSame(500);
        $this->assertStringContainsString("Can't refund the order with status pending", $client->getResponse()->getContent());
    }

    public function testRefundOrderCantBePaid(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $value = 'test-token';
        $this->setCsrfManagerWithToken($value);

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/pay", ['token' => $value]);

        $this->assertResponseStatusCodeSame(500);
        $this->assertStringContainsString("Can't set paid status for the order with status refunded", $client->getResponse()->getContent());
    }

    public function testRefundOrderCantBeFulfilled(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $value = 'test-token';
        $this->setCsrfManagerWithToken($value);

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $value]);

        $this->assertResponseStatusCodeSame(500);
        $this->assertStringContainsString("Can't fulfill the order with status refunded", $client->getResponse()->getContent());
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

    public function testPaidOrderToFulfillStatusChange(): void
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

    public function testPaidOrderToFulfillStatusChangeCreatesOneOrderFulfillment(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        self::getClient()
            ->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::FULFILLED, $order->getStatus());

        $fulfillmentRepo = self::getContainer()->get(OrderFulfillmentRepository::class);
        $fulfillments = $fulfillmentRepo->findAll();

        $this->assertCount(1, $fulfillments);
        $relatedOrder = $fulfillments[0]->getRelatedOrder();
        $this->assertSame($order->getId(), $relatedOrder->getId());
    }

    public function testOrderDoubleFulfillmentTriggersConstraintViolation(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);
        $orderFulfillment = new OrderFulfillment();
        $orderFulfillment->setRelatedOrder($order);
        $this->em->persist($orderFulfillment);
        $this->em->flush();

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        $fulfillEndpoint = "/orders/{$order->getId()}/fulfill";
        $client = self::getClient();
        $client->request('POST', $fulfillEndpoint, ['token' => $tokenValue]);
        $response = $client->getResponse();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString(
            'UniqueConstraintViolationException',
            $response->getContent()
        );
        self::assertStringContainsString(
            'duplicate key value violates unique constraint',
            $response->getContent()
        );
    }

    public function testPendingOrderCantBeFulfilledAndOrderFulfillmentNotCreated(): void
    {
        $order = OrderFactory::new()->create();

        $tokenValue = 'test-order';
        $this->setCsrfManagerWithToken($tokenValue);

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);
        $response = $client->getResponse();

        $this->assertStringContainsString("Can't fulfill the order with status pending", $response->getContent());
        $orderFulfillmentRepo = self::getContainer()->get(OrderFulfillmentRepository::class);
        $fulfillments = $orderFulfillmentRepo->findAll();

        $this->assertCount(0, $fulfillments);
    }

    public function testRefundedOrderCantBeFulfilledAndOrderFulfillmentNotCreated(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $tokenValue = 'test-order';
        $this->setCsrfManagerWithToken($tokenValue);

        $client = self::getClient();
        $client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);
        $response = $client->getResponse();

        $this->assertStringContainsString("Can't fulfill the order with status refunded", $response->getContent());
        $orderFulfillmentRepo = self::getContainer()->get(OrderFulfillmentRepository::class);
        $fulfillments = $orderFulfillmentRepo->findAll();

        $this->assertCount(0, $fulfillments);
    }

    public function testOrderPageShowsFulfillmentDetailsForOrderWithFulfillmentStatus(): void
    {
        $fulfillment = OrderFulfillmentFactory::new()
                        ->with([
                            'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2026-05-30 13:41:24')
                        ])
                        ->withOrderStatus(OrderStatus::FULFILLED)
                        ->create();
        $order = $fulfillment->getRelatedOrder();
        $this->assertSame(OrderStatus::FULFILLED, $order->getStatus());

        $client = self::getClient();
        $client->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorTextSame('span' , 'Status: fulfilled');
        $this->assertSelectorTextSame('[data-testid="fulfilled-date"]', '2026-05-30');
        $this->assertSelectorTextSame('[data-testid="fulfilled-time"]', '13:41:24');
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
