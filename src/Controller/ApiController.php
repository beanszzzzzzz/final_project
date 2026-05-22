<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CustomerNotifier;
use App\Dto\CustomerRequest;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\Exception\ValidationFailedException as SerializerValidationFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ApiController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->error('Email and password are required.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email format.', 400);
        }

        if (strlen($password) < 6) {
            return $this->error('Password must be at least 6 characters.', 400);
        }

        if ($userRepository->findOneBy(['email' => $email]) instanceof User) {
            return $this->error('Email is already registered.', 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, $password));

        $emailVerificationService->generateVerificationToken($user);

        $entityManager->persist($user);
        $entityManager->flush();

        try {
            $emailVerificationService->sendVerificationEmail($user);
        } catch (\Throwable $e) {
            return $this->error('Account created, but verification email could not be sent.', 500);
        }

        return $this->success('Registration successful. Please verify your email.', [
            'email' => $user->getEmail(),
            'verified' => $user->isVerified(),
        ], 201);
    }

    #[Route('/api/verify-email/{token}', name: 'api_verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): JsonResponse {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user instanceof User) {
            return $this->error('Invalid or expired token.', 400);
        }

        $emailVerificationService->verifyUser($user);
        $entityManager->flush();

        return $this->success('Email verified successfully.', [
            'email' => $user->getEmail(),
            'verified' => $user->isVerified(),
            'verifiedAt' => $user->getVerifiedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        JwtTokenService $jwtTokenService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->error('Email and password are required.', 400);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->error('Invalid credentials', 401);
        }

        if (!$user->isVerified()) {
            return $this->error('Email not verified. Please verify your account first.', 403);
        }

        $user->setLastLogin(new \DateTimeImmutable());
        $entityManager->flush();

        // Generate JWT token
        $token = $jwtTokenService->generateToken($user);

        return $this->success('Login successful.', [
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
                'verifiedAt' => $user->getVerifiedAt()?->format(DATE_ATOM),
            ],
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->error('Authenticated user not found.', 401);
        }

        return $this->success('Profile retrieved successfully.', [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'verified' => $user->isVerified(),
                'lastLogin' => $user->getLastLogin()?->format(DATE_ATOM),
            ],
        ]);
    }

    #[Route('/api/me/password', name: 'api_me_password', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->error('Authenticated user not found.', 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return $this->error('Current password, new password, and password confirmation are required.', 400);
        }

        if (!$hasher->isPasswordValid($user, $currentPassword)) {
            return $this->error('Current password is incorrect.', 401);
        }

        if (strlen($newPassword) < 6) {
            return $this->error('New password must be at least 6 characters.', 400);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->error('New passwords do not match.', 400);
        }

        $user->setPassword($hasher->hashPassword($user, $newPassword));
        $entityManager->flush();

        return $this->success('Password updated successfully.');
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/api/customers', name: 'api_customers_index', methods: ['GET'])]
    public function customers(CustomerRepository $customerRepository, Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        $customers = $customerRepository->findBy([], ['name' => 'ASC']);

        if ($query !== '') {
            $customers = array_values(array_filter($customers, static function (Customer $customer) use ($query): bool {
                return stripos((string) $customer->getName(), $query) !== false
                    || stripos((string) $customer->getEmail(), $query) !== false
                    || stripos((string) $customer->getPhone(), $query) !== false
                    || stripos((string) $customer->getAddress(), $query) !== false;
            }));
        }

        return $this->success('Customers retrieved successfully.', [
            'query' => $query,
            'count' => count($customers),
            'customers' => array_map(
                fn (Customer $customer) => $this->normalizeCustomerSummary($customer),
                $customers
            ),
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/api/customers/{id}', name: 'api_customers_show', methods: ['GET'])]
    public function customer(Customer $customer): JsonResponse
    {
        return $this->success('Customer retrieved successfully.', [
            'customer' => $this->normalizeCustomerDetail($customer),
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/api/customers', name: 'api_customers_create', methods: ['POST'])]
    public function createCustomer(
        Request $request,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        CustomerNotifier $notifier,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        $dto = $serializer->deserialize($request->getContent(), CustomerRequest::class, 'json');

        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            throw new SerializerValidationFailedException($dto, $violations);
        }

        $name = trim((string) ($dto->name ?? ''));
        $email = strtolower(trim((string) ($dto->email ?? '')));
        $phone = trim((string) ($dto->phone ?? ''));
        $address = trim((string) ($dto->address ?? ''));

        if ($customerRepository->findOneBy(['email' => $email]) instanceof Customer) {
            return $this->error('Customer email already exists.', 409);
        }

        $customer = new Customer();
        $customer->setName($name);
        $customer->setEmail($email);
        $customer->setPhone($phone);
        $customer->setAddress($address);

        $entityManager->persist($customer);
        $entityManager->flush();

        // Publish update for real-time subscribers (Mercure) if available
        $notifier->publish($customer, 'created');

        return $this->success('Customer created successfully.', [
            'customer' => $this->normalizeCustomerDetail($customer),
        ], 201);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/api/customers/{id}', name: 'api_customers_update', methods: ['PUT', 'PATCH'])]
    public function updateCustomer(
        Request $request,
        Customer $customer,
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        CustomerNotifier $notifier,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        // Deserialize incoming JSON to DTO and merge with existing values for partial updates
        $dto = $serializer->deserialize($request->getContent(), CustomerRequest::class, 'json');

        $dto->name = $dto->name ?? $customer->getName();
        $dto->email = $dto->email ?? $customer->getEmail();
        $dto->phone = $dto->phone ?? $customer->getPhone();
        $dto->address = $dto->address ?? $customer->getAddress();

        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            throw new SerializerValidationFailedException($dto, $violations);
        }

        $name = trim((string) $dto->name);
        $email = strtolower(trim((string) $dto->email));
        $phone = trim((string) $dto->phone);
        $address = trim((string) $dto->address);

        $existingCustomer = $customerRepository->findOneBy(['email' => $email]);
        if ($existingCustomer instanceof Customer && $existingCustomer->getId() !== $customer->getId()) {
            return $this->error('Customer email already exists.', 409);
        }

        $customer->setName($name);
        $customer->setEmail($email);
        $customer->setPhone($phone);
        $customer->setAddress($address);

        $entityManager->flush();

        $notifier->publish($customer, 'updated');

        return $this->success('Customer updated successfully.', [
            'customer' => $this->normalizeCustomerDetail($customer),
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/api/customers/{id}', name: 'api_customers_delete', methods: ['DELETE'])]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $entityManager, CustomerNotifier $notifier): JsonResponse
    {
        if ($customer->getOrders()->count() > 0) {
            return $this->error('Customer has orders and cannot be deleted.', 409);
        }

        $entityManager->remove($customer);
        $entityManager->flush();

        $notifier->publish($customer, 'deleted');

        return $this->success('Customer deleted successfully.');
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/api/customers/{id}/orders', name: 'api_customers_orders', methods: ['GET'])]
    public function customerOrders(Customer $customer): JsonResponse
    {
        $orders = $customer->getOrders()->toArray();

        usort($orders, static function (Order $left, Order $right): int {
            return ($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0);
        });

        return $this->success('Customer orders retrieved successfully.', [
            'customer' => $this->normalizeCustomerSummary($customer),
            'orders' => array_map(
                fn (Order $order) => $this->normalizeOrder($order),
                $orders
            ),
        ]);
    }

    private function success(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], $status);
    }

    private function normalizeCustomerSummary(Customer $customer): array
    {
        $orders = $customer->getOrders();
        $latestOrder = null;

        foreach ($orders as $order) {
            if (!$order instanceof Order) {
                continue;
            }

            if ($latestOrder === null || (($order->getCreatedAt()?->getTimestamp()) ?? 0) > (($latestOrder->getCreatedAt()?->getTimestamp()) ?? 0)) {
                $latestOrder = $order;
            }
        }

        return [
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'ordersCount' => $orders->count(),
            'latestOrderAt' => $latestOrder?->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function normalizeCustomerDetail(Customer $customer): array
    {
        $orders = $customer->getOrders()->toArray();

        usort($orders, static function (Order $left, Order $right): int {
            return ($right->getCreatedAt()?->getTimestamp() ?? 0) <=> ($left->getCreatedAt()?->getTimestamp() ?? 0);
        });

        return [
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'ordersCount' => count($orders),
            'orders' => array_map(
                fn (Order $order) => $this->normalizeOrder($order),
                $orders
            ),
        ];
    }

    private function normalizeOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'total' => (float) $order->getTotal(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], $status);
    }
}