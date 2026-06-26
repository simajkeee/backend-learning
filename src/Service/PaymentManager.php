<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Exception\InvalidPaymentProviderEventForOrder;
use App\Exception\OrderNotFoundException;
use App\Repository\PaymentProviderEventRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class PaymentManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentProviderEventRepository $eventRepo,
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
                $order = $em->find(Order::class, $orderId, LockMode::PESSIMISTIC_WRITE);
                if (null === $order) {
                    throw OrderNotFoundException::withDefaultMsg($orderId);
                }

                if ($order->isPaid()) {
                    $order->assertPaidEventMatches($providerEventId);

                    return;
                }

                $order->markPaid();

                $providerEventEntity = $this->eventRepo->findOneBy(['providerEventId' => $providerEventId]);
                if ($providerEventEntity instanceof PaymentProviderEvent) {
                    throw InvalidPaymentProviderEventForOrder::eventAlreadyBelongsToAnotherOrder(
                        $providerEventId,
                        $order->getId(),
                        $providerEventEntity->getRelatedOrder()->getId(),
                    );
                }

                $em->persist(new PaymentProviderEvent(
                    $order,
                    $providerEventId,
                    $payload,
                ));
            });
        } catch (UniqueConstraintViolationException $e) {
        }
    }
}
