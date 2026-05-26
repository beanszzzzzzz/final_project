<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController
{
    private ActivityLoggerService $activityLogger;

    public function __construct(ActivityLoggerService $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        try {
            $products = $productRepository->findAll();
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'The product list could not be loaded because the database is unavailable.');
            $products = [];
        }

        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($product);
                $entityManager->flush();

                // Log: Staff creates a record
                $this->activityLogger->logCreate(
                    'Product',
                    $product->getId(),
                    $product->getName()
                );

                $this->addFlash('success', 'Product created successfully!');
                return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Product could not be saved because the database connection failed.');
            }
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();

                // Log: Staff/Admin edits a record
                $this->activityLogger->logUpdate(
                    'Product',
                    $product->getId(),
                    $product->getName()
                );

                $this->addFlash('success', 'Product updated successfully!');
                return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Product could not be updated because the database connection failed.');
            }
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->getPayload()->getString('_token'))) {
            // Store info before deletion
            $productName = $product->getName();
            $productId = $product->getId();

            try {
                $entityManager->remove($product);
                $entityManager->flush();

                // Log: Staff/Admin deletes a record
                $this->activityLogger->logDelete(
                    'Product',
                    $productId,
                    $productName
                );

                $this->addFlash('success', 'Product deleted successfully!');
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Product could not be deleted because the database connection failed.');
            }
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
