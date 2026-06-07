<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\PaymentEvent;
use App\Enum\PaymentStatus;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class WebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route('/webhooks/fake-payment', name: 'app.webhooks.fake-payment', methods: [Request::METHOD_POST])]
    public function fakePayment(#[MapRequestPayload] PaymentEvent $paymentEvent): JsonResponse
    {
        if ($paymentEvent->status !== PaymentStatus::PAID->value) {
            return $this->json('Unprocessable request', 422);
        }

        try {
            $this->paymentService->processPaid(
                $paymentEvent->orderId,
                $paymentEvent->providerEventId,
                $this->serializer->serialize($paymentEvent, 'json')
            );

            return $this->json(['paid' => true]);
        } catch (\RuntimeException|\LogicException $e) {
            return $this->json(['paid' => false, 'reason' => $e->getMessage()]);
        }
    }
}
