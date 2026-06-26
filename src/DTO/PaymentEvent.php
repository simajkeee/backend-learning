<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\PaymentStatus;
use App\Validator\OrderPayable;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentEvent
{
    #[Assert\NotBlank]
    public string $providerEventId = '';

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[OrderPayable]
    public ?int $orderId = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [PaymentStatus::class, 'values'])]
    public string $status = '';
}
