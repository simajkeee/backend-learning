<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\OrderFulfillment;
use App\Enum\OrderStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<OrderFulfillment>
 */
final class OrderFulfillmentFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return OrderFulfillment::class;
    }

    public function withOrderStatus(OrderStatus $orderStatus)
    {
        return $this->with([
            'relatedOrder' => OrderFactory::new()->withStatus($orderStatus)
        ]);
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'relatedOrder' => OrderFactory::new(),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }
}
