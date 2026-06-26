<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function lockById(int $orderId): bool
    {
        $orderId = $this->getEntityManager()
                        ->getConnection()
                        ->fetchOne(
                            'SELECT id FROM orders WHERE id = :id FOR UPDATE',
                            ['id' => $orderId],
                        );

        return false !== $orderId;
    }
}
