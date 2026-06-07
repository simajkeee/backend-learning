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

    public static function fromArray(array $data): self
    {
        $paymentEvent = new self();
        $paymentEvent->providerEventId = $data['providerEventId'];
        $paymentEvent->orderId = $data['orderId'];
        $paymentEvent->status = $data['status'];

        return $paymentEvent;
    }

    public function __toArray(): array
    {
        return [
            'providerEventId' => $this->providerEventId,
            'orderId' => $this->orderId,
            'status' => $this->status,
        ];
    }
}
