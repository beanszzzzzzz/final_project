<?php

namespace App\Service;

use App\Entity\User;
use Brevo\Brevo;
use Brevo\Exceptions\BrevoApiException;
use Brevo\Exceptions\BrevoException;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailVerificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function generateVerificationToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        $user->setIsVerified(false);
        $user->setVerifiedAt(null);
        $user->setVerificationToken($token);

        return $token;
    }

    public function sendVerificationEmail(User $user): void
    {
        $token = $user->getVerificationToken();
        if ($token === null || $token === '') {
            $token = $this->generateVerificationToken($user);
        }

        $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $apiKey = (string) ($_ENV['BREVO_API_KEY'] ?? '');
        if ($apiKey !== '') {
            $this->sendUsingBrevo($apiKey, (string) $user->getEmail(), $verificationUrl);
            return;
        }

        $mailerDsn = (string) ($_ENV['MAILER_DSN'] ?? '');
        if ($mailerDsn === '' || str_starts_with($mailerDsn, 'null://')) {
            throw new \RuntimeException('Email delivery is not configured. Configure BREVO_API_KEY or MAILER_DSN.');
        }

        $email = (new Email())
            ->from('no-reply@binscafe.local')
            ->to((string) $user->getEmail())
            ->subject('Verify your Bins Cafe account')
            ->html(sprintf(
                '<p>Welcome to Bins Cafe.</p><p>Please verify your account by clicking the link below:</p><p><a href="%s">Verify Email</a></p>',
                htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8')
            ));

        $this->mailer->send($email);
    }

    private function sendUsingBrevo(string $apiKey, string $recipientEmail, string $verificationUrl): void
    {
        try {
            $brevo = new Brevo($apiKey);

            $emailRequest = new SendTransacEmailRequest([
                'to' => [new SendTransacEmailRequestToItem([
                    'email' => $recipientEmail,
                ])],
                'sender' => new SendTransacEmailRequestSender([
                    'name' => 'Bins Cafe',
                    'email' => 'vincentdelostrico79@gmail.com',
                ]),
                'subject' => 'Verify your Bins Cafe account',
                'htmlContent' => sprintf(
                    '<p>Welcome to Bins Cafe.</p><p>Please verify your account by clicking the link below:</p><p><a href="%s">Verify Email</a></p>',
                    htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8')
                ),
            ]);

            $brevo->transactionalEmails->sendTransacEmail($emailRequest);
        } catch (BrevoApiException | BrevoException $e) {
            throw new \RuntimeException('Failed to send verification email via Brevo.', 0, $e);
        }
    }

    public function verifyUser(User $user): void
    {
        $user->setIsVerified(true);
        $user->setVerifiedAt(new \DateTime());
        $user->setVerificationToken(null);
    }
}
