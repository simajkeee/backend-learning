<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotPayableException;
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
                /** @var Order $order */
                $order = $this->orderRepo->find($orderId);
                if (null === $order) {
                    throw OrderNotFoundException::withDefaultMsg($orderId);
                }

                if ($order->isPaid()) {
                    if ($order->hasProviderEventId($providerEventId)) {
                        return;
                    }

                    throw OrderNotPayableException::withDefaultMsg($order->getStatus());
                }

                $order->markPaid();

                $em->persist(new PaymentProviderEvent(
                    $order, $providerEventId, $payload
                ));
            });
        } catch (UniqueConstraintViolationException $e) {
            // do nothing
        }
    }
}
