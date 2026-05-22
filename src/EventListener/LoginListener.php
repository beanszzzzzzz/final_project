<?php
// src/EventListener/LoginSuccessListener.php

namespace App\EventListener;

use App\Entity\User;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginListener
{
    private ActivityLoggerService $activityLogger;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ActivityLoggerService $activityLogger,
        EntityManagerInterface $entityManager
    ) {
        $this->activityLogger = $activityLogger;
        $this->entityManager = $entityManager;
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Update last login time
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();

        // Log the login activity
        $this->activityLogger->logLogin($user);
    }
}