<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Exception\InvalidPaymentProviderEventForOrder;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepository;
use App\Repository\PaymentProviderEventRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function processPaid(string $providerEventId, int $orderId, int $total, string $currency, string $payload): void
    {
        $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use (
            $providerEventId,
            $orderId,
            $payload,
        ): void {
            /**
             * @var OrderRepository $orderRepo
             */
            $orderRepo = $em->getRepository(Order::class);
            $locked = $orderRepo->lockById($orderId);
            if (!$locked) {
                throw OrderNotFoundException::withDefaultMsg($orderId);
            }

            $order = $orderRepo->find($orderId);
            if ($order->isPaid()) {
                $order->assertPaidEventMatches($providerEventId);

                return;
            }

            /**
             * @var PaymentProviderEventRepository $paymentProviderRepo
             */
            $paymentProviderRepo = $em->getRepository(PaymentProviderEvent::class);
            $providerEventEntity = $paymentProviderRepo->findOneByProviderEventId($providerEventId);
            if ($providerEventEntity instanceof PaymentProviderEvent) {
                throw InvalidPaymentProviderEventForOrder::eventAlreadyBelongsToAnotherOrder(
                    $providerEventId,
                    $order->getId(),
                    $providerEventEntity->getRelatedOrder()->getId(),
                );
            }

            $order->markPaid();

            $em->persist(new PaymentProviderEvent(
                $order,
                $providerEventId,
                $payload,
            ));
        });
    }
}
