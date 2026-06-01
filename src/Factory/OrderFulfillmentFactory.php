<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\OrderFulfillment;
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

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'order' => OrderFactory::new(),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'updatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}
