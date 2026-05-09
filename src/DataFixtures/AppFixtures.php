<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < 3; $i++) {
             $product = new Product();
            $product->setName('product '.$i);
            $product->setDescription('product text '.$i);
            $product->setPrice((string)mt_rand(10, 100));
            $manager->persist($product);
            $manager->flush();
        }
    }
}
