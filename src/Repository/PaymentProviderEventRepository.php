<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentProviderEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentProviderEvent>
 */
class PaymentProviderEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentProviderEvent::class);
    }
}
