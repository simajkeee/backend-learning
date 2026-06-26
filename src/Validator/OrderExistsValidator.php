<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class OrderExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    /**
     * @param OrderExists $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ('' === $value || !is_numeric($value)) {
            return;
        }

        $order = $this->orderRepository->find((int) $value);
        if ($order instanceof Order) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->addViolation();
    }
}
