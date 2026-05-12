<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    public function testProductListReturns200AndShowProduct()
    {
        static::createClient()
            ->request('GET', '/products');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'product 0');
    }

    public function testProductDetailPageReturns200AndTitleIsThere()
    {
        static::createClient()
            ->request('GET', '/products/product-0');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'product 0');

    }
}
