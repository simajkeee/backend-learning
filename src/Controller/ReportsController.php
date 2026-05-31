<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderFulfillmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReportsController extends AbstractController
{
    #[Route('/reports/orders/fulfilled')]
    public function fulfilledOrders(OrderFulfillmentRepository $repo): Response
    {
        return $this->render('report/fulfilled.html.twig', [
            'report' => $repo->findForFulfilledOrdersReport()
        ]);
    }
}
