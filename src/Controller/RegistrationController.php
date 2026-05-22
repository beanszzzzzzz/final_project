<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_USER']);

            $emailVerificationService->generateVerificationToken($user);

            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $emailVerificationService->sendVerificationEmail($user);
                $this->addFlash('success', 'Registration successful. Please verify your email before logging in.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Account created, but verification email could not be sent. Please contact support.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, UserRepository $userRepository, EntityManagerInterface $entityManager, EmailVerificationService $emailVerificationService): Response
    {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user instanceof User) {
            $this->addFlash('error', 'Invalid or expired verification link.');
            return $this->redirectToRoute('app_login');
        }

        $emailVerificationService->verifyUser($user);
        $entityManager->flush();

        $this->addFlash('success', 'Email verified successfully. You can now log in.');
        return $this->redirectToRoute('app_login');
    }
}
