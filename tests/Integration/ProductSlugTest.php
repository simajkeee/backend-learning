<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductSlugTest extends KernelTestCase
{
    public function testProductSlugIsGenerated(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $product = new Product('Test Product', 1000);

        $em->persist($product);
        $em->flush();

        $this->assertNotNull($product->getId());
        $this->assertSame('test-product', $product->getSlug());
    }
}
