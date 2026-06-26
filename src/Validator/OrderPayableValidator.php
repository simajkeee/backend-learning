<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class OrderPayableValidator extends ConstraintValidator
{
    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    /**
     * @param OrderPayable $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ('' === $value || !is_numeric($value)) {
            return;
        }

        $order = $this->orderRepository->find((int) $value);
        if ($order instanceof Order && OrderStatus::PENDING === $order->getStatus()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->addViolation();
    }
}
