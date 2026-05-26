<?php

namespace App\Factory;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Order>
 */
final class OrderFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static function withStatus(OrderStatus $orderStatus): Order
    {
        $factory = match($orderStatus) {
            OrderStatus::PAID => self::new()->paid(),
            OrderStatus::FULFILLED => self::new()->fulfilled(),
            OrderStatus::REFUNDED => self::new()->refunded(),
            default => self::new(),
        };

        return $factory->create();
    }

    #[\Override]
    public static function class(): string
    {
        return Order::class;
    }

    public function paid(): self
    {
        return $this
             ->afterInstantiate(function(Order $order): void {
                $order->markPaid();
             });
    }

    public function fulfilled(): self
    {
        return $this
            ->afterInstantiate(function(Order $order): void {
                $order->markPaid();
                $order->fulfill();
            });
    }

    public function refunded(): self
    {
        return $this
            ->afterInstantiate(function(Order $order): void {
                $order->markPaid();
                $order->refund();
            });
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'product' => ProductFactory::new(),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
//             ->afterInstantiate(function(Order $order): void {
//
//             })
        ;
    }
}
