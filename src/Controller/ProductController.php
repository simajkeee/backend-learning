<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/products', name: 'app.products')]
    public function index(ProductRepository $repository): Response
    {
        return $this->render('product/index.html.twig', ['products' => $repository->findAll()]);
    }

    #[Route('/products/{slug}', name: 'app.product')]
    public function show(string $slug, ProductRepository $repo): Response
    {
        $product = $repo->findOneBy(['slug' => $slug]);
        if (!$product) {
            throw new NotFoundHttpException();
        }

        return $this->render('product/show.html.twig', ['product' => $product]);

    }
}