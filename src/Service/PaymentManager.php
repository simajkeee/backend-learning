<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PaymentProviderEvent;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotPayableException;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class PaymentManager
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function processPaid(int $orderId, string $providerEventId, string $payload): void
    {
        try {
            $this->em->wrapInTransaction(function (EntityManagerInterface $em) use (
                $orderId,
                $providerEventId,
                $payload,
            ): void {
                $order = $this->orderRepo->find($orderId);
                if (null === $order) {
                    throw OrderNotFoundException::withDefaultMsg($orderId);
                }

                if ($order->isPaid()) {
                    $order->assertPaidEventMatches($providerEventId);

                    return;
                }

                $order->markPaid();

                $em->persist(new PaymentProviderEvent(
                    $order, $providerEventId, $payload
                ));
            });
        } catch (UniqueConstraintViolationException $e) {
            $order = $this->orderRepo->find($orderId);
            if ($order->getPaymentProviderEvent()?->getProviderEventId() !== $providerEventId) {
                throw OrderNotPayableException::withDefaultMsg($order->getStatus());
            }
        }
    }
}
