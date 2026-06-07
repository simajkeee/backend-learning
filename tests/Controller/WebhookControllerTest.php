<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DTO\PaymentEvent;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Factory\OrderFactory;
use App\Repository\PaymentProviderEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookControllerTest extends WebTestCase
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
        $this->assertSame(OrderStatus::PAID, $order->getStatus());

        $providerEvent = $this->paymentProviderEventRepo->findOneBy(['providerEventId' => $providerEventId]);
        $this->assertNotNull($providerEvent);
        $this->assertSame($providerEventId, $providerEvent->getProviderEventId());
        $this->assertNotEmpty($providerEvent->getPayload());
        $this->assertSame(json_encode((array) PaymentEvent::fromArray($payload)), $providerEvent->getPayload());
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

    public function testConcurrentRequestsAreSafelyHandledByPaymentEndopint(): void
    {
        $order = OrderFactory::new()->create();
        $httpClient = static::getClient();

        $responses = [];
        for ($i = 0; $i < 2; ++$i) {
            $httpClient->request('POST', '/webhooks/fake-payment', [
                'providerEventId' => 'evt_123',
                'orderId' => $order->getId(),
                'status' => 'paid',
            ]);
            $responses[] = $httpClient->getResponse();
        }

        foreach ($responses as $response) {
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('{"paid":true}', $response->getContent(false));
        }
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

        $this->assertResponseIsUnprocessable();
        $this->assertSame('{"paid":false,"reason":"Order 1 not found"}', $response->getContent());
    }

    public function testOrderNotPendingStatusIsUnprocessable(): void
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

        $this->assertResponseIsUnprocessable();
        $this->assertSame('{"paid":false,"reason":"Can\u0027t set paid status for the order with status fulfilled"}', $response->getContent());
    }
}
