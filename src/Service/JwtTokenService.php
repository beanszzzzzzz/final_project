<?php

namespace App\Service;

use App\Entity\User;

/**
 * JWT Token Generator Service
 * Generates simple JWT tokens for mobile app authentication
 */
class JwtTokenService
{
    private string $secret;
    private int $expirationTime;

    public function __construct(string $jwtSecret = 'your-secret-key-change-in-env', int $expirationHours = 24)
    {
        $this->secret = $jwtSecret;
        $this->expirationTime = $expirationHours * 3600; // Convert hours to seconds
    }

    /**
     * Generate a JWT token for a user
     */
    public function generateToken(User $user): string
    {
        $now = time();
        $exp = $now + $this->expirationTime;

        // JWT Header
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        // JWT Payload
        $payload = [
            'iss' => 'final_project',
            'iat' => $now,
            'exp' => $exp,
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];

        // Encode header and payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // Create signature
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secret,
            true
        );
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Verify and decode a JWT token
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secret,
            true
        );
        $signatureExpected = $this->base64UrlEncode($signature);

        if ($signatureEncoded !== $signatureExpected) {
            return null;
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        // Check expiration
        if ($payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
