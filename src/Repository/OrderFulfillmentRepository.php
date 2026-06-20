<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderFulfillment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderFulfillment>
 */
class OrderFulfillmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderFulfillment::class);
    }

    /**
     * @return list<OrderFulfillment>
     */
    public function findForFulfilledOrdersReport(): array
    {
        $qb = $this->createQueryBuilder('f')
                ->innerJoin('f.relatedOrder', 'o')
                ->addSelect('o')
                ->innerJoin('o.product', 'p')
                ->addSelect('p')
                ->orderBy('f.createdAt', 'DESC')
                ->addOrderBy('f.id', 'DESC');

        return $qb->getQuery()
                  ->getResult();
    }
}
