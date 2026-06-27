<?php

declare(strict_types=1);

namespace App\Tests\Feature\Controller;

use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use App\Repository\OrderFulfillmentRepository;
use App\Repository\OrderRepository;
use App\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class OrderControllerTest extends TestCase
{
    private KernelBrowser $client;

    private OrderRepository $orderRepo;

    private EntityManagerInterface $em;

    public function setUp(): void
    {
        $this->client = self::createClient();
        $container = static::getContainer();

        $this->orderRepo = $container->get(OrderRepository::class);
        $this->em = $container->get(EntityManagerInterface::class);
    }

    public function testProductOrderCreatedAndRedirected(): void
    {
        $crawler = $this->client->request('GET', '/products/product-0');
        $form = $crawler->selectButton('Buy')->form();
        $this->client->submit($form);

        $orders = $this->orderRepo->findAll();

        $this->assertCount(1, $orders);
        $this->assertResponseRedirects("/orders/{$orders[0]->getId()}");
    }

    public function testRedirectShowsProductNameAndStatus(): void
    {
        $this->client->followRedirects();

        $crawler = $this->client->request('GET', '/products/product-0');
        $form = $crawler->selectButton('Buy')->form();
        $this->client->submit($form);

        $orders = $this->orderRepo->findAll();
        $this->assertCount(1, $orders);

        $order = $orders[0];
        $product = $order->getProduct();

        $this->assertSelectorTextContains('p', $product->getName());
        $this->assertSelectorTextContains('span', $order->getStatus()->value);
    }

    public function testFakeSlugNotFoundOnOrderCreation(): void
    {
        $this->client
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
        $order = OrderFactory::new()->create();
        $id = $order->getId();

        $crawler = $this->client->request('GET', "/orders/{$id}");
        $form = $crawler->selectButton('Mark as paid')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects("/orders/{$id}");

        $order = $this->orderRepo->findOneBy(['id' => $id]);
        $this->assertSame(OrderStatus::PAID, $order->getStatus());
    }

    public function testFollowRedirectShowsPaidStatus(): void
    {
        $this->client->followRedirects();

        $order = OrderFactory::new()->create();
        $id = $order->getId();

        $crawler = $this->client->request('GET', "/orders/{$id}");
        $form = $crawler->selectButton('Mark as paid')->form();
        $this->client->submit($form);

        $this->assertSelectorTextSame('span', 'Status: paid');
    }

    public function testInvalidOrderIdReturns404(): void
    {
        $this->client->request('POST', '/orders/-999/pay');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPaidOrderPageShowsFulfillAction(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

        $this->client
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('button', 'Fulfill');
    }

    public function testPendingOrderPageDoesntShowFulfillAction(): void
    {
        $order = OrderFactory::new()->create();

        $this->client
            ->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextNotContains('button', 'Fulfill');
    }

    public function testOrderIsFulfilledAndRedirected(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);
        $id = $order->getId();
        $this->assertTrue($order->isPaid());

        $this->client->followRedirects();

        $crawler = $this->client->request('GET', "/orders/{$id}");
        $form = $crawler->selectButton('Fulfill')->form();
        $this->client->submit($form);

        $order = $this->orderRepo->findOneBy(['id' => $id]);

        $this->assertSame(OrderStatus::FULFILLED, $order->getStatus());
        $this->assertSelectorTextSame('span', 'Status: fulfilled');
    }

    public function testFulfillEndpointReturns500WhenOrderPending(): void
    {
        $order = OrderFactory::new()->create();

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill", [
            'token' => $tokenValue,
        ]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_fulfillable',
            ],
            $this->jsonResponse(),
        );
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

        $crawler = $this->client->request('GET', "/orders/{$order->getId()}");
        $form = $crawler->selectButton('Refund')->form();
        $this->client->submit($form);

        $order = $this->orderRepo->find($order->getId());
        $this->assertSame(OrderStatus::REFUNDED, $order->getStatus());

        $this->assertResponseRedirects("/orders/{$order->getId()}");
    }

    public function testAfterRefundedActionShowsRefundedStatus(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

        $this->client->followRedirects();

        $crawler = $this->client->request('GET', "/orders/{$order->getId()}");
        $form = $crawler->selectButton('Refund')->form();
        $this->client->submit($form);

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

        $this->client->request('POST', "/orders/{$order->getId()}/pay");

        $order = $this->orderRepo->find($order->getId());

        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $response = $this->client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
    }

    public function testMissingCsrfDoesntChangePaidOrderStatusToFulfilled(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill");

        $order = $this->orderRepo->find($order->getId());

        $this->assertSame(OrderStatus::PAID, $order->getStatus());
        $response = $this->client->getResponse();
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token', $response->getContent());
    }

    public function testPendingOrderCantBeRefunded(): void
    {
        $order = OrderFactory::new()->create();

        $value = 'test-token';
        $this->setCsrfManagerWithToken($value);

        $this->client->request('POST', "/orders/{$order->getId()}/refund", ['token' => $value]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_refundable',
            ],
            $this->jsonResponse(),
        );
    }

    public function testRefundOrderCantBePaid(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $value = 'test-token';
        $this->setCsrfManagerWithToken($value);

        $this->client->request('POST', "/orders/{$order->getId()}/pay", ['token' => $value]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_payable',
            ],
            $this->jsonResponse(),
        );
    }

    public function testRefundOrderCantBeFulfilled(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $value = 'test-token';
        $this->setCsrfManagerWithToken($value);

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $value]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_fulfillable',
            ],
            $this->jsonResponse(),
        );
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

    public function testOrderDoubleFulfillmentDoesNotCreateSecondFulfillment(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);

        $tokenValue = 'test-token';
        $this->setCsrfManagerWithToken($tokenValue);

        $this->client->disableReboot();

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);
        $this->assertResponseRedirects("/orders/{$order->getId()}");

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);

        $this->assertResponseStatusCodeSame(409);

        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_fulfillable',
            ],
            $this->jsonResponse(),
        );

        $fulfillmentRepo = self::getContainer()->get(OrderFulfillmentRepository::class);
        $this->assertCount(1, $fulfillmentRepo->findAll());
    }

    public function testPendingOrderCantBeFulfilledAndOrderFulfillmentNotCreated(): void
    {
        $order = OrderFactory::new()->create();

        $tokenValue = 'test-order';
        $this->setCsrfManagerWithToken($tokenValue);

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);

        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_fulfillable',
            ],
            $this->jsonResponse(),
        );
        $orderFulfillmentRepo = self::getContainer()->get(OrderFulfillmentRepository::class);
        $fulfillments = $orderFulfillmentRepo->findAll();

        $this->assertCount(0, $fulfillments);
    }

    public function testRefundedOrderCantBeFulfilledAndOrderFulfillmentNotCreated(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);

        $tokenValue = 'test-order';
        $this->setCsrfManagerWithToken($tokenValue);

        $this->client->request('POST', "/orders/{$order->getId()}/fulfill", ['token' => $tokenValue]);

        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_fulfillable',
            ],
            $this->jsonResponse(),
        );
        $orderFulfillmentRepo = self::getContainer()->get(OrderFulfillmentRepository::class);
        $fulfillments = $orderFulfillmentRepo->findAll();

        $this->assertCount(0, $fulfillments);
    }

    public function testOrderPageShowsFulfillmentDetailsForOrderWithFulfillmentStatus(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PAID);
        $fulfillment = $order->fulfill();
        $fulfillment->setCreatedAt(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2026-05-30 13:41:24'));
        $this->em->flush();
        $this->em->clear();

        $this->client->request('GET', "/orders/{$order->getId()}");

        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorTextSame('span', 'Status: fulfilled');
        $this->assertSelectorTextSame('[data-testid="fulfilled-date"]', '2026-05-30');
        $this->assertSelectorTextSame('[data-testid="fulfilled-time"]', '13:41:24');
    }
}
