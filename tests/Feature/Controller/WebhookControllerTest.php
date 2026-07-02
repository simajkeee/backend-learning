<?php

declare(strict_types=1);

namespace App\Tests\Feature\Controller;

use App\DTO\PaymentEvent;
use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Enum\Currency;
use App\Enum\OrderStatus;
use App\Exception\InvalidPaymentProviderEventForOrder;
use App\Exception\OrderNotPayableException;
use App\Factory\OrderFactory;
use App\Repository\PaymentProviderEventRepository;
use App\Tests\TestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Messenger\Test\Transport\TestTransport;

class WebhookControllerTest extends TestCase
{
    use InteractsWithMessenger;

    private EntityManagerInterface $em;

    private PaymentProviderEventRepository $paymentProviderEventRepo;

    private TestTransport $transport;

    public function setUp(): void
    {
        self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->paymentProviderEventRepo = self::getContainer()->get(PaymentProviderEventRepository::class);
        $this->transport = $this->transport('redis');
    }

    public function testOrderStatusChangedToPaidAndPaymentProviderEventIsCreated(): void
    {
        $price = 10555;
        $providerEventId = 'evt_123';
        $order = OrderFactory::new()
                             ->withProductPrice($price)
                             ->create();
        $payload = [
            'providerEventId' => $providerEventId,
            'orderId' => $order->getId(),
            'status' => 'paid',
            'total' => $price,
            'currency' => Currency::USD->value,
        ];

        $this->transport->queue()->assertEmpty();

        self::getClient()
            ->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->transport->processOrFail();

        $this->em->clear();

        $freshOrder = $this->em->getRepository(Order::class)->find($order->getId());
        $this->assertSame(OrderStatus::PAID, $freshOrder->getStatus());

        $providerEvent = $this->paymentProviderEventRepo->findOneBy(['providerEventId' => $providerEventId]);
        $this->assertNotNull($providerEvent);
        $this->assertSame($providerEventId, $providerEvent->getProviderEventId());
        $this->assertNotEmpty($providerEvent->getPayload());

        $paymentEvent = new PaymentEvent();
        $paymentEvent->providerEventId = $payload['providerEventId'];
        $paymentEvent->orderId = $payload['orderId'];
        $paymentEvent->status = $payload['status'];
        $paymentEvent->total = $payload['total'];
        $paymentEvent->currency = $payload['currency'];
        $this->assertSame(json_encode($paymentEvent), $providerEvent->getPayload());
    }

    public function testDuplicateEventIsSafeAndDoesntCreateDoublePaymentProviderEvent(): void
    {
        $price = 10555;
        $providerEventId = 'evt_123';
        $order = OrderFactory::new()
                             ->withProductPrice($price)
                             ->create();
        $payload = [
            'providerEventId' => $providerEventId,
            'orderId' => $order->getId(),
            'status' => 'paid',
            'total' => $price,
            'currency' => Currency::USD->value,
        ];

        self::getClient()
            ->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        self::getClient()
            ->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->transport->queue()->assertCount(1);

        $this->transport->processOrFail();

        $providerEvents = $this->paymentProviderEventRepo->findOneByProviderEventId($providerEventId);
        $this->assertNotNull($providerEvents);
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
        $this->assertStringContainsString('This value should not be blank', $response->getContent());

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
            'total' => 100,
            'currency' => Currency::USD->value,
        ];
        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseIsUnprocessable();
    }

    public function testRepeatedPaidWebhookWithDifferentProviderEventIdIsHandled(): void
    {
        /** @var TestHandler $logHandler */
        $logHandler = self::getContainer()->get('monolog.handler.test');
        $logHandler->clear();

        $price = 10555;
        $order = OrderFactory::new()->withProductPrice($price)->create();
        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
            'total' => $price,
            'currency' => Currency::USD->value,
        ];

        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $this->jsonResponse();

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->assertTrue($response['processing']);
        $this->transport->queue()->assertCount(1);
        $this->transport->processOrFail();

        $payload['providerEventId'] = 'changed_evt_123';
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->transport->queue()->assertCount(1);

        $this->transport->processOrFail();

        $this->transport->acknowledged()->assertCount(2);
        $this->transport->rejected()->assertCount(0);

        $this->assertTrue(
            $logHandler->hasRecordThatPasses(
                static function (LogRecord $record) use ($order): bool {
                    return str_contains($record->message, "Can't process the order {$order->getId()}")
                        && ($record->context['exception'] ?? null) === OrderNotPayableException::class;
                },
                Level::Warning,
            ),
            'Expected warning log for invalid provider event id.',
        );
    }

    public function testRepeatedPaidWebhookWithDifferentOrderIsHandled(): void
    {
        /** @var TestHandler $logHandler */
        $logHandler = self::getContainer()->get('monolog.handler.test');
        $logHandler->clear();

        $price = 10555;
        $order = OrderFactory::new()->withProductPrice($price)->create();
        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
            'total' => $price,
            'currency' => Currency::USD->value,
        ];

        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $response = $this->jsonResponse();

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->assertTrue($response['processing']);
        $this->transport->queue()->assertCount(1);
        $this->transport->processOrFail();

        $order2 = OrderFactory::new()->withProductPrice($price)->create();
        $payload['orderId'] = $order2->getId();
        $client->request('POST', '/webhooks/fake-payment', $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->transport->queue()->assertCount(1);

        $this->transport->processOrFail();

        $this->transport->acknowledged()->assertCount(2);
        $this->transport->rejected()->assertCount(0);

        $this->assertTrue(
            $logHandler->hasRecordThatPasses(
                static function (LogRecord $record) use ($order2): bool {
                    return str_contains($record->message, "Payment provider payload mismatch {$order2->getId()}")
                        && ($record->context['exception'] ?? null) === InvalidPaymentProviderEventForOrder::class;
                },
                Level::Error,
            ),
            'Expected error log for invalid provider event id.',
        );
    }

    public function testRepeatedPaidWebhookWithSameDataIsIdempotent(): void
    {
        /** @var TestHandler $logHandler */
        $logHandler = self::getContainer()->get('monolog.handler.test');
        $logHandler->clear();

        $price = 10555;
        $order = OrderFactory::new()
                    ->withProductPrice($price)
                    ->create();

        $payload = [
            'orderId' => $order->getId(),
            'providerEventId' => 'evt_123',
            'status' => 'paid',
            'total' => $price,
            'currency' => Currency::USD->value,
        ];

        $client = self::getClient();
        $client->request('POST', '/webhooks/fake-payment', $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->transport->queue()->assertCount(1);
        $this->transport->processOrFail();

        $client->request('POST', '/webhooks/fake-payment', $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->transport->queue()->assertCount(1);

        $this->transport->processOrFail();

        $this->transport->acknowledged()->assertCount(2);
        $this->transport->rejected()->assertCount(0);

        $this->assertFalse($logHandler->hasRecords(Level::Warning));

        $connection = self::getContainer()->get(Connection::class);
        $eventCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM payment_provider_event');
        $this->assertSame(1, $eventCount);
    }
}
