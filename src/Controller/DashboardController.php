<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    public function __construct(private ActivityLoggerService $activityLogger) {}

    #[IsGranted('ROLE_STAFF')]
    #[Route(name: 'app_dashboard_index', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        StockRepository $stockRepository,
        CustomerRepository $customerRepository,
        OrderRepository $orderRepository
    ): Response {
        try {
            // Count total products
            $totalProducts = $productRepository->count([]) ?? 0;

            // Get stock summary
            $stockSummary = $stockRepository->getStockSummary() ?? ['totalItems' => 0];
            $totalStocks = $stockSummary['totalItems'] ?? 0;

            // Count total customers
            $totalCustomers = $customerRepository->count([]) ?? 0;

            // Get total revenue
            $totalRevenue = $orderRepository->getTotalRevenue() ?? 0;

            // Get revenue change data
            $revenueChangeData = $orderRepository->getRevenueChangePercentage() ?? [];
            $todayRevenue = $revenueChangeData['today'] ?? 0;
            $yesterdayRevenue = $revenueChangeData['yesterday'] ?? 0;
            $revenueChange = $revenueChangeData['change'] ?? 0;
            $isRevenueIncrease = $revenueChangeData['isIncrease'] ?? false;

            // Get sales data for chart (last 7 days)
            $salesData = $orderRepository->getSalesDataLast7Days() ?? [];

            // Get recent orders
            $recentOrders = $orderRepository->getRecentOrders(5) ?? [];

            // Get today's orders count
            $todayOrdersCount = $orderRepository->countTodayOrders() ?? 0;

            // Get recent products (last 5)
            $recentProducts = $productRepository->findBy(
                [],
                ['id' => 'DESC'],
                5
            ) ?? [];

            // Get low stock items (quantity <= reorder level)
            $lowStockItems = $stockRepository->findLowStockItems(5) ?? [];

            // Get out of stock items
            $outOfStockItems = $stockRepository->findOutOfStockItems(5) ?? [];
        } catch (\Exception $e) {
            // If any query fails, use defaults - allow dashboard to load anyway
            $totalProducts = $totalStocks = $totalCustomers = $totalRevenue = 0;
            $todayRevenue = $yesterdayRevenue = $revenueChange = 0;
            $isRevenueIncrease = false;
            $stockSummary = ['totalItems' => 0];
            $salesData = $recentOrders = $recentProducts = $lowStockItems = $outOfStockItems = [];
            $todayOrdersCount = 0;
        }

        return $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'totalStocks' => $totalStocks,
            'totalCustomers' => $totalCustomers,
            'totalRevenue' => $totalRevenue,
            'todayRevenue' => $todayRevenue,
            'revenueChange' => $revenueChange,
            'isRevenueIncrease' => $isRevenueIncrease,
            'salesData' => $salesData,
            'recentOrders' => $recentOrders,
            'recentProducts' => $recentProducts,
            'lowStockItems' => $lowStockItems,
            'outOfStockItems' => $outOfStockItems,
            'stockSummary' => $stockSummary,
            'todayOrdersCount' => $todayOrdersCount,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/products', name: 'app_dashboard_products', methods: ['GET'])]
    public function products(ProductRepository $productRepository): Response
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

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/products/new', name: 'app_dashboard_product_new', methods: ['GET', 'POST'])]
    public function newProduct(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($product);
                $entityManager->flush();

                $this->activityLogger->logCreate(
                    'Product',
                    (int) $product->getId(),
                    (string) $product->getName()
                );

                return $this->redirectToRoute('app_dashboard_products', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Product could not be saved because the database connection failed.');
            }
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/products/{id}', name: 'app_dashboard_product_show', methods: ['GET'])]
    public function showProduct(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/products/{id}/edit', name: 'app_dashboard_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();

                $this->activityLogger->logUpdate(
                    'Product',
                    (int) $product->getId(),
                    (string) $product->getName()
                );

                return $this->redirectToRoute('app_dashboard_products', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $exception) {
                $this->addFlash('error', 'Product could not be updated because the database connection failed.');
            }
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }
}
