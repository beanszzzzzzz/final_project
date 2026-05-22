<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/orders')]
#[IsGranted('ROLE_STAFF')]
class OrderController extends AbstractController
{
    public function __construct(private ActivityLoggerService $activityLogger)
    {
    }

    #[Route('/', name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        return $this->render('order/index.html.twig', [
            'orders' => $orderRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $customerId = $request->request->get('customer_id');
            $products = $request->request->all('products') ?? [];
            
            if (empty($customerId) || empty($products)) {
                $this->addFlash('error', 'Please select a customer and at least one product.');
                return $this->redirectToRoute('app_order_new');
            }

            $customer = $customerRepository->find($customerId);
            if (!$customer) {
                $this->addFlash('error', 'Customer not found.');
                return $this->redirectToRoute('app_order_new');
            }

            // Calculate total
            $total = 0;
            foreach ($products as $productData) {
                if (!empty($productData['id']) && !empty($productData['quantity'])) {
                    $product = $productRepository->find($productData['id']);
                    if ($product) {
                        $total += $product->getPrice() * (int)$productData['quantity'];
                    }
                }
            }

            // Create order
            $order = new Order();
            $order->setOrderNumber('ORD-' . strtoupper(uniqid()));
            $order->setCustomer($customer);
            $order->setTotal($total);
            $order->setStatus('pending');
            $order->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($order);
            $entityManager->flush();

            $this->activityLogger->logCreate(
                'Order',
                (int) $order->getId(),
                (string) $order->getOrderNumber()
            );

            $this->addFlash('success', 'Order created successfully!');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        return $this->render('order/new.html.twig', [
            'customers' => $customerRepository->findAll(),
            'products' => $productRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        // Only allow editing if order is pending
        if ($order->getStatus() !== 'pending') {
            $this->addFlash('error', 'Cannot edit completed or cancelled orders.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        if ($request->isMethod('POST')) {
            $status = $request->request->get('status');
            
            if (in_array($status, ['pending', 'completed', 'cancelled'])) {
                $order->setStatus($status);
                $entityManager->flush();

                $this->activityLogger->logUpdate(
                    'Order',
                    (int) $order->getId(),
                    (string) $order->getOrderNumber() . ' status=' . $status
                );

                $this->addFlash('success', 'Order updated successfully!');
                return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
            }
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            // Only allow deleting pending orders
            if ($order->getStatus() === 'pending') {
                $orderId = (int) $order->getId();
                $orderNumber = (string) $order->getOrderNumber();

                $entityManager->remove($order);
                $entityManager->flush();

                $this->activityLogger->logDelete('Order', $orderId, $orderNumber);

                $this->addFlash('success', 'Order deleted successfully!');
            } else {
                $this->addFlash('error', 'Cannot delete completed or cancelled orders.');
            }
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }
}