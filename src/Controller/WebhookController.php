<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\PaymentEvent;
use App\Message\PaymentProcessing;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/webhooks/fake-payment', name: 'app.webhooks.fake-payment', methods: [Request::METHOD_POST])]
    public function fakePayment(#[MapRequestPayload] PaymentEvent $paymentEvent): JsonResponse
    {
        try {
            $idempotencyKey = sprintf(
                'payment-processing.order:%d.provider_event:%s',
                $paymentEvent->orderId,
                $paymentEvent->providerEventId,
            );

            $this->bus->dispatch(new PaymentProcessing(
                $this->serializer->serialize($paymentEvent, 'json'),
                $idempotencyKey
            ), [
                new DeduplicateStamp('dispatch.'.$idempotencyKey),
            ]);

            return $this->json(['processing' => true], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            $this->logger->error('Payment webhook dispatch failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->json(
                ['processing' => false],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }
}
