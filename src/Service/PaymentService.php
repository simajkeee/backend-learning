<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Exception\OrderNotFoundException;
use App\Exception\OrderNotPayableException;
use App\Repository\OrderRepository;
use App\Repository\PaymentProviderEventRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly PaymentProviderEventRepository $paymentProviderEventRepo,
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

                $order->assertIsPaidHasProviderEventId($providerEventId);

                $order->markPaid();

                $em->persist(new PaymentProviderEvent(
                    $order, $providerEventId, $payload
                ));
            });
        } catch (UniqueConstraintViolationException $e) {
            $paymentProviderEvent = $this->paymentProviderEventRepo->findOneBy([
                'providerEventId' => $providerEventId,
            ]);

            if (null === $paymentProviderEvent) {
                $order = $this->orderRepo->find($orderId);

                throw OrderNotPayableException::withDefaultMsg($order->getStatus());
            }
        }
    }
}
