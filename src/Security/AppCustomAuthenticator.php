<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $payload = $request->getPayload();

        // Support both modern field names (email/password) and Symfony defaults (_username/_password).
        $email = trim($payload->getString('email', $payload->getString('_username')));
        $password = $payload->getString('password', $payload->getString('_password'));

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        // Check user status before authentication
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if ($user && !$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account has been deactivated. Please contact administrator.');
        }

        if ($user && !$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('Please verify your email address before logging in.');
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $payload->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Redirect based on role
        if ($user instanceof User) {
            $roles = $user->getRoles();
            
            // Admin goes to dashboard
            if (in_array('ROLE_ADMIN', $roles)) {
                return new RedirectResponse($this->urlGenerator->generate('app_dashboard_index'));
            }
            
            // Staff goes to dashboard
            if (in_array('ROLE_STAFF', $roles)) {
                return new RedirectResponse($this->urlGenerator->generate('app_dashboard_index'));
            }
            
            // Customer goes to home page
            if (in_array('ROLE_USER', $roles)) {
                return new RedirectResponse('/');
            }
        }

        // Default fallback to home
        return new RedirectResponse('/');
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}