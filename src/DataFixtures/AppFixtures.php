<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\ProductFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 3; ++$i) {
            ProductFactory::new()->with([
                'name' => 'product '.$i,
                'description' => 'product text '.$i,
                'price' => (string) random_int(10, 100),
            ])->create();
        }
    }
}
