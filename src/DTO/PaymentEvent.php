<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\PaymentStatus;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentEvent
{
    #[Assert\NotBlank]
    public string $providerEventId = '';

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $orderId = 0;

    #[Assert\Choice(callback: [PaymentStatus::class, 'values'])]
    public string $status = '';
}
