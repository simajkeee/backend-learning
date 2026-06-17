<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DTO\PaymentEvent;
use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use App\Repository\PaymentProviderEventRepository;
use App\Tests\TestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class WebhookControllerTest extends TestCase
{
    private EntityManagerInterface $em;

    private PaymentProviderEventRepository $paymentProviderEventRepo;

    public function setUp(): void
    {
        self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->paymentProviderEventRepo = self::getContainer()->get(PaymentProviderEventRepository::class);
    }

    public function testOrderStatusChangedToPaidAndPaymentProviderEvenIsCreated(): void
    {
        $order = OrderFactory::new()->create();
        $providerEventId = 'evt_123';
        $payload = [
            'providerEventId' => $providerEventId,
            'orderId' => $order->getId(),
            'status' => 'paid',
        ];

        self::getClient()
            ->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseIsSuccessful();

        $orderRepo = $this->em->getRepository(Order::class);
        $order = $orderRepo->find($order->getId());
        $this->assertNotNull($order);
        $this->assertTrue($order->isPaid());

        $providerEvent = $this->paymentProviderEventRepo->findOneBy(['providerEventId' => $providerEventId]);
        $this->assertNotNull($providerEvent);
        $this->assertSame($providerEventId, $providerEvent->getProviderEventId());
        $this->assertNotEmpty($providerEvent->getPayload());

        $paymentEvent = new PaymentEvent();
        $paymentEvent->providerEventId = $payload['providerEventId'];
        $paymentEvent->orderId = $payload['orderId'];
        $paymentEvent->status = $payload['status'];
        $this->assertSame(json_encode($paymentEvent), $providerEvent->getPayload());
    }

    public function testDuplicateEventIsSafeAndDoesntCreateDoublePaymentProviderEvent(): void
    {
        $order = OrderFactory::new()->create();
        $providerEventId = 'evt_123';
        $payload = [
            'providerEventId' => $providerEventId,
            'orderId' => $order->getId(),
            'status' => 'paid',
        ];

        self::getClient()
            ->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseIsSuccessful();
        $providerEvents = $this->paymentProviderEventRepo->findBy(['providerEventId' => $providerEventId]);
        $this->assertCount(1, $providerEvents);

        self::getClient()
            ->request('POST', '/webhooks/fake-payment', $payload);
        $providerEvents = $this->paymentProviderEventRepo->findBy(['providerEventId' => $providerEventId]);
        $this->assertCount(1, $providerEvents);
    }

    public function testConstraintViolationIsTriggeredForTheSameEventId(): void
    {
        $eventId = 'evt_123';

        $paymentProviderEvent = new PaymentProviderEvent(OrderFactory::new()->create(), $eventId, '{}');
        $this->em->persist($paymentProviderEvent);
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $paymentProviderEvent = new PaymentProviderEvent(OrderFactory::new()->create(), $eventId, '{}');
        $this->em->persist($paymentProviderEvent);
        $this->em->flush();
    }

    public function testConstraintViolationIsTriggeredForTheSameOrder(): void
    {
        $order = OrderFactory::new()->create();

        $paymentProviderEvent = new PaymentProviderEvent($order, 'evt_123', '{}');
        $this->em->persist($paymentProviderEvent);
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $paymentProviderEvent = new PaymentProviderEvent($order, 'evt_223', '{}');
        $this->em->persist($paymentProviderEvent);
        $this->em->flush();
    }

    public function testInvalidPayloadValidationIsTriggered(): void
    {
        $payload = [
            'orderId' => '',
            'providerEventId' => 'evt_123',
            'status' => 'paid',
        ];

        $client = self::getClient();

        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();
        $this->assertResponseIsUnprocessable();
        $this->assertStringContainsString('This value should be of type int', $response->getContent());

        $payload = [
            'orderId' => 1,
            'providerEventId' => '',
            'status' => 'paid',
        ];
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();
        $this->assertResponseIsUnprocessable();
        $this->assertStringContainsString('This value should not be blank', $response->getContent());

        $payload = [
            'orderId' => 1,
            'providerEventId' => 'evt_123',
            'status' => 'test',
        ];
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();
        $this->assertResponseIsUnprocessable();
        $this->assertStringContainsString('The value you selected is not a valid choice', $response->getContent());
    }

    public function testMissingOrderIsUnprocessable(): void
    {
        $payload = [
            'orderId' => 1,
            'providerEventId' => 'evt_123',
            'status' => 'paid',
        ];
        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString('"order_not_found"', $response->getContent());
    }

    public function testOrderFulfilledStatusIsUnprocessable(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::FULFILLED);
        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
        ];
        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(409);
        $this->assertStringContainsString('"order_not_payable"', $response->getContent());
    }

    public function testOrderRefundedStatusIsUnprocessable(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::REFUNDED);
        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
        ];
        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(409);
        $this->assertStringContainsString('"order_not_payable"', $response->getContent());
    }

    public function testOrderPaidStatusIsSuccessfulAndIdempotent(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PENDING);
        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
        ];

        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(200);
        $this->assertTrue(json_decode($response->getContent())->paid);

        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(200);
        $this->assertTrue(json_decode($response->getContent())->paid);
    }

    public function testOrderPaidStatusWithDifferentEventIdsReturn409CodeWithErrorCode(): void
    {
        $order = OrderFactory::createWithStatus(OrderStatus::PENDING);
        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
        ];

        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $client->getResponse();

        $this->assertResponseStatusCodeSame(200);
        $this->assertTrue(json_decode($response->getContent())->paid);

        $payload['providerEventId'] = 'changed_evt_123';
        $client->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseStatusCodeSame(409);
        $this->assertArraysAreEqual(
            [
                'success' => false,
                'error_code' => 'order_not_payable',
            ],
            $this->jsonResponse()
        );
    }
}
