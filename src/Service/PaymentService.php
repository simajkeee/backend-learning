<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
        try {
            $this->em->wrapInTransaction(function (EntityManagerInterface $em) use (
                $orderId,
                $providerEventId,
                $payload,
            ): void {
                $em->persist(new PaymentProviderEvent(
                    $providerEventId, $payload
                ));

                /** @var Order $order */
                $order = $this->orderRepo->find($orderId);
                if (null === $order) {
                    throw new \RuntimeException("Order {$orderId} not found");
                }

                if (OrderStatus::PAID === $order->getStatus()) {
                    return;
                }

                $order->markPaid();
            });
        } catch (UniqueConstraintViolationException $e) {
            // do nothing
        }
    }
}
