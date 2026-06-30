<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\InvalidPaymentProviderEventForOrder;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotPayableException;
use App\Message\PaymentProcessing;
use App\Service\PaymentManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PaymentProcessing $message): void
    {
        $lock = $this->lockFactory->createLock('handler.'.$message->getIdempotencyKey(), 120);
        if (!$lock->acquire()) {
            throw new RecoverableMessageHandlingException(sprintf('Order %d is already being processed.', $message->getOrderId()));
        }

        try {
            $this->paymentService->processPaid(
                $message->getProviderEventId(),
                $message->getOrderId(),
                $message->getTotal(),
                $message->getCurrency(),
                $message->getContent(),
            );
        } catch (OrderNotFoundException|OrderNotPayableException|InvalidPaymentProviderEventForOrder $e) {
            $this->logger->warning("Can't process the order {$message->getOrderId()}", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->warning('Payment event conflict', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
    }
}
