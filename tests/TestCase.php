<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

abstract class TestCase extends WebTestCase
{
    public function setCsrfManagerWithToken(string $tokenValue): void
    {
        $csrfManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfManager
            ->method('isTokenValid')
            ->willReturn(true);
        $csrfManager
            ->method('getToken')
            ->willReturnCallback(
                fn (string $tokenId) => new CsrfToken($tokenId, $tokenValue),
            );
        self::getContainer()->set(CsrfTokenManagerInterface::class, $csrfManager);
    }

    /**
     * @return array<mixed>
     */
    protected function jsonResponse(): array
    {
        return json_decode(
            self::getClient()->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
