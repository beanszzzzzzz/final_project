<?php

namespace App\Security;

use App\Service\JwtTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use App\Repository\UserRepository;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JwtTokenService $jwtTokenService,
        private UserRepository $userRepository,
    ) {}

    public function supports(Request $request): ?bool
    {
        // Only support API routes
        return str_starts_with($request->getPathInfo(), '/api/') &&
            $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('No Bearer token provided');
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        // Verify token
        try {
            $payload = $this->jwtTokenService->verifyToken($token);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid or expired token');
        }

        if (!$payload) {
            throw new AuthenticationException('Invalid or expired token');
        }

        // Get user from payload
        $userId = $payload['user_id'] ?? null;

        if (!$userId) {
            throw new AuthenticationException('Invalid token payload');
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, function ($userId) {
                try {
                    return $this->userRepository->find($userId);
                } catch (\Exception $e) {
                    // If DB is unavailable, treat as authentication failure
                    return null;
                }
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Continue with the request
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(
            json_encode(['error' => $exception->getMessageKey()]),
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/json']
        );
    }
}
