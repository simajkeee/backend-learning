<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    public function __construct(public EntityManagerInterface $em, public LoggerInterface $logger)
    {
    }

    #[Route('/products/{slug:product}/orders', name: 'app.order.create')]
    public function create(Product $product): Response
    {
        try {
            $order = new Order();
            $order->setProduct($product);
            $this->em->persist($order);
            $this->em->flush();

            return $this->redirectToRoute('app.order', ['id' => $order->getId()]);
        } catch (Exception $e) {
            $this->logger->error("Order was not create for the product id {$product->getId()} the reason: {$e->getMessage()}");

            return $this->redirectToRoute('app.product', ['slug' => $product->getSlug()]);
        }
    }

    #[Route('/orders/{id}', 'app.order')]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', ['order' => $order]);
    }

}
