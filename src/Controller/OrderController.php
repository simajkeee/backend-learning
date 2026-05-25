<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class OrderController extends AbstractController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    #[Route('/orders/{id}', 'app.order')]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', ['order' => $order]);
    }

    #[IsCsrfTokenValid(new Expression('"buy-product-" ~ args["product"].getId()'), tokenKey: 'token')]
    #[Route('/products/{slug:product}/orders', name: 'app.order.create', methods: [Request::METHOD_POST])]
    public function create(Product $product): Response
    {
        return $this->redirectToRoute('app.order', [
            'id' => $this->orderService->create($product)->getId()
        ]);
    }

    #[IsCsrfTokenValid(new Expression('"pay-order-" ~ args["order"].getId()'), tokenKey: 'token')]
    #[Route('/orders/{id}/pay', name: 'app.order.pay', methods: [Request::METHOD_POST])]
    public function pay(Order $order): Response
    {
        return $this->redirectToRoute('app.order', [
            'id' => $this->orderService->markPaid($order)->getId()
        ]);
    }

    #[IsCsrfTokenValid(new Expression('"fulfill-order-" ~ args["order"].getId()'), tokenKey: 'token')]
    #[Route('/orders/{id}/fulfill', name: 'app.order.fulfill', methods: [Request::METHOD_POST])]
    public function fulfill(Order $order): Response
    {
        return $this->redirectToRoute('app.order', [
            'id' => $this->orderService->fulfill($order)->getId()
        ]);
    }

    #[IsCsrfTokenValid(new Expression('"refund-order-" ~ args["order"].getId()'), tokenKey: 'token')]
    #[Route('/orders/{id}/refund', name: 'app.order.refund', methods: [Request::METHOD_POST])]
    public function refund(Order $order): Response
    {
        return $this->redirectToRoute('app.order', [
            'id' => $this->orderService->refund($order)->getId()
        ]);
    }
}
