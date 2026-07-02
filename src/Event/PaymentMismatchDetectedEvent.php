<?php

declare(strict_types=1);

namespace App\Event;

use App\DTO\PaymentMismatchDetails;
use Symfony\Contracts\EventDispatcher\Event;

class PaymentMismatchDetectedEvent extends Event
{
    public function __construct(private readonly PaymentMismatchDetails $paymentMismatchDetails)
    {
    }

    public function getPaymentMismatchDetails(): PaymentMismatchDetails
    {
        return $this->paymentMismatchDetails;
    }
}
