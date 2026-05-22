<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class StaffGoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = $this->fetchAccessToken($this->getGoogleClient());

        $googleUser = $this->getGoogleClient()->fetchUserFromToken($accessToken);
        if (!$googleUser instanceof GoogleUser) {
            throw new CustomUserMessageAuthenticationException('Google login failed. Please try again.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '') {
            throw new CustomUserMessageAuthenticationException('Google account did not provide an email address.');
        }

        if (!$this->isAllowedStaffEmail($email)) {
            throw new CustomUserMessageAuthenticationException('This Google account is not allowed for staff login.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email) {
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_STAFF']);
                    $user->setIsActive(true);
                    $user->setIsVerified(true);
                    $user->setVerifiedAt(new \DateTime());
                    $user->setPassword(password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT));

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Your account has been deactivated. Please contact administrator.');
                }

                if (!in_array('ROLE_STAFF', $user->getRoles(), true) && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                    $user->setRoles(['ROLE_STAFF']);
                    $user->setIsVerified(true);
                    $user->setVerifiedAt(new \DateTime());
                    $this->entityManager->flush();
                }

                return $user;
            }),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_dashboard_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessageKey());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function getGoogleClient(): GoogleClient
    {
        return $this->clientRegistry->getClient('google_main');
    }

    private function isAllowedStaffEmail(string $email): bool
    {
        $allowedList = array_filter(array_map('trim', explode(',', (string) ($_ENV['STAFF_GOOGLE_EMAILS'] ?? ''))));
        $allowedList = array_map('strtolower', $allowedList);

        if (in_array($email, $allowedList, true)) {
            return true;
        }

        $allowedDomain = strtolower(trim((string) ($_ENV['STAFF_GOOGLE_DOMAIN'] ?? '')));
        if ($allowedDomain !== '' && str_ends_with($email, '@' . $allowedDomain)) {
            return true;
        }

        return false;
    }
}
