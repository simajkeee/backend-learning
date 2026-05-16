<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/products/{slug:product}/orders', name: 'app.order.create', methods: ['POST'])]
    public function create(Product $product): Response
    {
        $order = new Order();
        $order->setProduct($product);
        $this->em->persist($order);
        $this->em->flush();

        return $this->redirectToRoute('app.order', ['id' => $order->getId()]);
    }

    #[Route('/orders/{id}', 'app.order')]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', ['order' => $order]);
    }

    #[Route('/orders/{id}/pay', name: 'app.order.pay', methods: ['POST'])]
    public function orderPay(Order $order): Response
    {
        $order->markPaid();
        $this->em->flush();

        return $this->redirectToRoute('app.order', ['id' => $order->getId()]);
    }
}
