<?php
require __DIR__.'/../vendor/autoload.php';

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

$key = getenv('MERCURE_KEY') ?: 'dev_mercure_secret_key_0123456789abcdefghijklmnop';
$config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($key));
$now = new DateTimeImmutable();
$token = $config->builder()
    ->issuedBy('final_project')
    ->issuedAt($now)
    ->expiresAt($now->modify('+1 hour'))
    ->withClaim('mercure', ['publish' => ['*'], 'subscribe' => ['*']])
    ->getToken($config->signer(), $config->signingKey());

echo $token->toString();
