<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard/stocks')]
class StockController extends AbstractController
{
    public function __construct(private ActivityLoggerService $activityLogger)
    {
    }

    #[Route('', name: 'app_dashboard_stocks', methods: ['GET'])]
    public function index(StockRepository $stockRepository): Response
    {
        return $this->render('stock/index.html.twig', [
            'stocks' => $stockRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_dashboard_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($stock);
            $entityManager->flush();

            $this->activityLogger->logCreate(
                'Stock',
                (int) $stock->getId(),
                (string) $stock->getProduct()?->getName()
            );

            $this->addFlash('success', 'Stock created successfully!');
            return $this->redirectToRoute('app_dashboard_stocks', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dashboard_stock_show', methods: ['GET'])]
    public function show(Stock $stock): Response
    {
        return $this->render('stock/show.html.twig', [
            'stock' => $stock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dashboard_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->activityLogger->logUpdate(
                'Stock',
                (int) $stock->getId(),
                (string) $stock->getProduct()?->getName()
            );

            $this->addFlash('success', 'Stock updated successfully!');
            return $this->redirectToRoute('app_dashboard_stocks', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->request->get('_token'))) {
            $stockId = (int) $stock->getId();
            $stockName = (string) $stock->getProduct()?->getName();

            $entityManager->remove($stock);
            $entityManager->flush();

            $this->activityLogger->logDelete('Stock', $stockId, $stockName);

            $this->addFlash('success', 'Stock deleted successfully!');
        }

        return $this->redirectToRoute('app_dashboard_stocks', [], Response::HTTP_SEE_OTHER);
    }
}