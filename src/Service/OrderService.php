<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderFulfillment;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function create(Product $product): Order
    {
        $order = new Order($product);
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    public function markPaid(Order $order): Order
    {
        $order->markPaid();
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    public function fulfill(Order $order): Order
    {
        $this->em->wrapInTransaction(function (EntityManagerInterface $em) use ($order) {
            $order->fulfill();
            $fulfillment = new OrderFulfillment();
            $fulfillment->setRelatedOrder($order);

            $em->persist($fulfillment);
        });

        return $order;
    }

    public function refund(Order $order): Order
    {
        $order->refund();
        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
