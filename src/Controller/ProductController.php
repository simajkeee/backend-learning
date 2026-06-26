<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/products', name: 'app.products')]
    public function index(ProductRepository $repository): Response
    {
        return $this->render('product/index.html.twig', ['products' => $repository->findAll()]);
    }

    #[Route('/products/{slug:product}', name: 'app.product')]
    public function show(Product $product): Response
    {
        return $this->render(
            'product/show.html.twig',
            ['product' => $product],
        );
    }
}
