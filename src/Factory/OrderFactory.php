<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Order>
 */
final class OrderFactory extends PersistentObjectFactory
{
    public static function createWithStatus(OrderStatus $orderStatus): Order
    {
        return self::new()->withStatus($orderStatus)->create();
    }

    public function withStatus(OrderStatus $orderStatus): self
    {
        return match ($orderStatus) {
            OrderStatus::PAID => self::new()->paid(),
            OrderStatus::FULFILLED => self::new()->fulfilled(),
            OrderStatus::REFUNDED => self::new()->refunded(),
            default => self::new(),
        };
    }

    #[\Override]
    public static function class(): string
    {
        return Order::class;
    }

    public function paid(): self
    {
        return $this
             ->afterInstantiate(function (Order $order): void {
                 $order->markPaid();
             });
    }

    public function fulfilled(): self
    {
        return $this
            ->afterInstantiate(function (Order $order): void {
                $order->markPaid();
                $order->fulfill();
            });
    }

    public function refunded(): self
    {
        return $this
            ->afterInstantiate(function (Order $order): void {
                $order->markPaid();
                $order->refund();
            });
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'product' => ProductFactory::new(),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }
}
