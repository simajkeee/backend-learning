<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\PaymentEvent;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotPayableException;
use App\Message\PaymentProcessing;
use App\Service\PaymentManager;
use App\Service\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
class PaymentProcessingHandler
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly PaymentManager $paymentService,
        private readonly Serializer $serializer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PaymentProcessing $message): void
    {
        $content = $message->getContent();
        $paymentEvent = $this->serializer->deserializeJson($content, PaymentEvent::class);

        $lock = $this->lockFactory->createLock(
            'handler.'.$message->getIdempotencyKey(), 120);
        if (!$lock->acquire()) {
            throw new RecoverableMessageHandlingException(sprintf('Order %d is already being processed.', $paymentEvent->orderId));
        }

        try {
            $this->paymentService->processPaid(
                $paymentEvent->orderId,
                $paymentEvent->providerEventId,
                $content,
            );
        } catch (OrderNotFoundException $e) {
            $this->logger->warning($e->getMessage());
        } catch (OrderNotPayableException $e) {
        } finally {
            $lock->release();
        }
    }
}
