<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Serializer\SerializerInterface;

class Serializer
{
    private const string JSON = 'json';

    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function deserializeJson(mixed $data, string $type, array $context = []): mixed
    {
        return $this->serializer->deserialize($data, $type, self::JSON, $context);
    }
}
