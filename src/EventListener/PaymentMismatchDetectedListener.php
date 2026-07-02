<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\PaymentMismatchDetectedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class PaymentMismatchDetectedListener
{
    #[AsEventListener]
    public function onPaymentMismatchDetectedEvent(PaymentMismatchDetectedEvent $event): void
    {
    }
}
