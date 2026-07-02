<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\PaymentMismatchDetails;
use App\Entity\Order;
use App\Entity\PaymentProviderEvent;
use App\Event\PaymentMismatchDetectedEvent;
use App\Exception\InvalidOrderCurrency;
use App\Exception\InvalidOrderTotal;
use App\Exception\InvalidPaymentProviderEventForOrder;
use App\Exception\OrderNotFoundException;
use App\Repository\OrderRepository;
use App\Repository\PaymentProviderEventRepository;
use App\ValueObject\Currency;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PaymentManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepo,
        private readonly PaymentProviderEventRepository $paymentProviderEventRepo,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function processPaid(string $providerEventId, int $orderId, int $total, string $currency, string $payload): void
    {
        try {
            $order = null;
            $this->em->wrapInTransaction(function (EntityManagerInterface $em) use (
                &$order,
                $providerEventId,
                $orderId,
                $total,
                $currency,
                $payload,
            ): void {
                $order = $this->orderRepo->findByIdForUpdate($orderId);
                if (!$order) {
                    throw OrderNotFoundException::withDefaultMsg($orderId);
                }

                if ($order->isPaid()) {
                    $order->assertPaidEventMatches($providerEventId);

                    return;
                }

                $this->validateTotal($order, $providerEventId, $total, $orderId);
                $this->validateCurrency($order, $providerEventId, $currency, $orderId);
                $this->validateProviderNotExist($providerEventId, $orderId);

                $order->markPaid();

                $em->persist(new PaymentProviderEvent(
                    $order,
                    $providerEventId,
                    $payload,
                ));
            });
        } catch (InvalidOrderTotal|InvalidOrderCurrency $e) {
            $paymentMismatchDetails = new PaymentMismatchDetails(
                $providerEventId,
                $orderId,
                $order->getTotal(),
                $order->getCurrency()->value,
                $total,
                $currency,
                $e->getErrorCode(),
            );

            $this->dispatcher->dispatch(new PaymentMismatchDetectedEvent($paymentMismatchDetails));

            throw $e;
        }
    }

    private function validateTotal(Order $order, string $providerEventId, int $total, int $orderId): void
    {
        if (!$order->hasEqualTotalTo(Money::fromInt($total))) {
            throw InvalidOrderTotal::forOrder(
                $providerEventId,
                $total,
                $orderId,
            );
        }
    }

    private function validateCurrency(Order $order, string $providerEventId, string $currency, int $orderId): void
    {
        if (!$order->hasSameCurrencyAs(Currency::fromString($currency))) {
            throw InvalidOrderCurrency::forOrder(
                $providerEventId,
                $currency,
                $orderId,
            );
        }
    }

    private function validateProviderNotExist(string $providerEventId, int $orderId): void
    {
        $providerEventEntity = $this->paymentProviderEventRepo->findOneByProviderEventId($providerEventId);
        if ($providerEventEntity instanceof PaymentProviderEvent) {
            throw InvalidPaymentProviderEventForOrder::eventAlreadyBelongsToAnotherOrder(
                $providerEventId,
                $orderId,
                $providerEventEntity->getRelatedOrder()->getId(),
            );
        }
    }
}
