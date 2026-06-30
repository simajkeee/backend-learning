<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Product;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Product>
 */
final class ProductFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Product::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->text(100),
            'price' => random_int(10000, 100000),
            'description' => self::faker()->text(255),
        ];
    }
}
