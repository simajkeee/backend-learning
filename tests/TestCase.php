<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class TestCase extends WebTestCase
{
    protected function jsonResponse(): array
    {
        return json_decode(
            self::getClient()->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }
}
