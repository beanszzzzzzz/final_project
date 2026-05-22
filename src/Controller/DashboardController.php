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
    public function __construct(private ActivityLoggerService $activityLogger)
    {
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route(name: 'app_dashboard_index', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        StockRepository $stockRepository,
        CustomerRepository $customerRepository,
        OrderRepository $orderRepository
    ): Response
    {
        // Count total products
        $totalProducts = $productRepository->count([]);
        
        // Get stock summary
        $stockSummary = $stockRepository->getStockSummary();
        $totalStocks = $stockSummary['totalItems'];
        
        // Count total customers
        $totalCustomers = $customerRepository->count([]);
        
        // Get total revenue
        $totalRevenue = $orderRepository->getTotalRevenue();
        
        // Get revenue change data
        $revenueChangeData = $orderRepository->getRevenueChangePercentage();
        $todayRevenue = $revenueChangeData['today'];
        $yesterdayRevenue = $revenueChangeData['yesterday'];
        $revenueChange = $revenueChangeData['change'];
        $isRevenueIncrease = $revenueChangeData['isIncrease'];
        
        // Get sales data for chart (last 7 days)
        $salesData = $orderRepository->getSalesDataLast7Days();
        
        // Get recent orders
        $recentOrders = $orderRepository->getRecentOrders(5);
        
        // Get today's orders count
        $todayOrdersCount = $orderRepository->countTodayOrders();
        
        // Get recent products (last 5)
        $recentProducts = $productRepository->findBy(
            [],
            ['id' => 'DESC'],
            5
        );
        
        // Get low stock items (quantity <= reorder level)
        $lowStockItems = $stockRepository->findLowStockItems(5);
        
        // Get out of stock items
        $outOfStockItems = $stockRepository->findOutOfStockItems(5);
        
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
        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findAll(),
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
            $entityManager->persist($product);
            $entityManager->flush();

            $this->activityLogger->logCreate(
                'Product',
                (int) $product->getId(),
                (string) $product->getName()
            );

            return $this->redirectToRoute('app_dashboard_products', [], Response::HTTP_SEE_OTHER);
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
            $entityManager->flush();

            $this->activityLogger->logUpdate(
                'Product',
                (int) $product->getId(),
                (string) $product->getName()
            );

            return $this->redirectToRoute('app_dashboard_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

}