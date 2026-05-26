<?php
// src/Service/ActivityLoggerService.php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLoggerService
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private RequestStack $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        RequestStack $requestStack
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    /**
     * Log user login
     */
    public function logLogin(User $user): void
    {
        $this->log(
            $user,
            'LOGIN',
            "User login"
        );
    }

    /**
     * Log user logout
     */
    public function logLogout(User $user): void
    {
        $this->log(
            $user,
            'LOGOUT',
            "User logout"
        );
    }

    /**
     * Log user creation (Admin creates user)
     */
    public function logUserCreate(User $createdUser): void
    {
        $this->log(
            $this->security->getUser(),
            'CREATE',
            "User: {$createdUser->getEmail()} (ID: {$createdUser->getId()})"
        );
    }

    /**
     * Log user deletion (Admin deletes user)
     */
    public function logUserDelete(int $deletedUserId, string $deletedUserEmail): void
    {
        $this->log(
            $this->security->getUser(),
            'DELETE',
            "User: {$deletedUserEmail} (ID: {$deletedUserId})"
        );
    }

    /**
     * Log user update (Admin updates user)
     */
    public function logUserUpdate(User $updatedUser): void
    {
        $this->log(
            $this->security->getUser(),
            'UPDATE',
            "User: {$updatedUser->getEmail()} (ID: {$updatedUser->getId()})"
        );
    }

    /**
     * Log record creation (Staff creates record)
     */
    public function logCreate(string $entityType, int $entityId, string $entityName): void
    {
        $this->log(
            $this->security->getUser(),
            'CREATE',
            "{$entityType}: {$entityName} (ID: {$entityId})"
        );
    }

    /**
     * Log record update (Staff/Admin updates record)
     */
    public function logUpdate(string $entityType, int $entityId, string $entityName): void
    {
        $this->log(
            $this->security->getUser(),
            'UPDATE',
            "{$entityType}: {$entityName} (ID: {$entityId})"
        );
    }

    /**
     * Log record deletion (Staff/Admin deletes record)
     */
    public function logDelete(string $entityType, int $entityId, string $entityName): void
    {
        $this->log(
            $this->security->getUser(),
            'DELETE',
            "{$entityType}: {$entityName} (ID: {$entityId})"
        );
    }

    /**
     * Main logging method - stores all required fields
     */
    private function log(?User $user, string $action, string $targetData): void
    {
        if (!$user instanceof User) {
            return; // Can't log without a user
        }

        try {
            $activityLog = new ActivityLog();

            // Required fields
            $activityLog->setUserId($user->getId());
            $activityLog->setUsername($user->getEmail());
            $activityLog->setRole($this->getUserPrimaryRole($user));
            $activityLog->setAction($action);
            $activityLog->setTargetData($targetData);
            $activityLog->setDateTime(new \DateTime());

            // Optional fields - Keep user relationship
            $activityLog->setUser($user);

            // Add IP and User Agent if available
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $activityLog->setIpAddress($request->getClientIp());
                $activityLog->setUserAgent($request->headers->get('User-Agent'));
            }

            $this->entityManager->persist($activityLog);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Log the error but don't crash the application
            error_log('Activity logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Get user's primary role
     */
    private function getUserPrimaryRole(User $user): string
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return 'ROLE_ADMIN';
        }

        if (in_array('ROLE_STAFF', $roles)) {
            return 'ROLE_STAFF';
        }

        return 'ROLE_USER';
    }
}
