<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function processPaid(int $orderId, string $providerEventId, string $payload): void
    {
        $this->em->wrapInTransaction(function (EntityManagerInterface $em) use (
            $orderId,
            $providerEventId,
            $payload,
        ): void {
            /** @var Order $order */
            $order = $this->orderRepo->find($orderId);
            if (null === $order) {
                throw new \RuntimeException("Order {$orderId} not found");
            }

            $order->markPaid();

            $paymentProviderEvent = new PaymentProviderEvent($providerEventId, $payload);

            $em->persist($paymentProviderEvent);
        });
    }
}
