<?php

namespace App\Controller;

use Brevo\Brevo;
use Brevo\Exceptions\BrevoApiException;
use Brevo\Exceptions\BrevoException;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact/send', name: 'app_contact_send', methods: ['POST'])]
    public function send(Request $request): Response
    {
        $firstName = trim((string) $request->request->get('first_name', ''));
        $lastName = trim((string) $request->request->get('last_name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $subject = trim((string) $request->request->get('subject', 'General Inquiry'));
        $message = trim((string) $request->request->get('message', ''));

        if ($firstName === '' || $email === '' || $message === '') {
            $this->addFlash('error', 'Please fill in First Name, Email, and Message before submitting.');
            return $this->redirectToRoute('app_contact');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please provide a valid email address.');
            return $this->redirectToRoute('app_contact');
        }

        $apiKey = (string) ($_ENV['BREVO_API_KEY'] ?? '');
        if ($apiKey === '') {
            $this->addFlash('error', 'Contact service is not configured yet. Please try again later.');
            return $this->redirectToRoute('app_contact');
        }

        $fullName = trim($firstName . ' ' . $lastName);
        $safeName = htmlspecialchars($fullName === '' ? $firstName : $fullName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        try {
            $brevo = new Brevo($apiKey);

            $emailRequest = new SendTransacEmailRequest([
                'to' => [new SendTransacEmailRequestToItem([
                    'email' => 'vincentdelostrico79@gmail.com',
                    'name' => 'Bins Cafe'
                ])],
                'sender' => new SendTransacEmailRequestSender([
                    'name' => 'Bins Cafe Contact Form',
                    'email' => 'vincentdelostrico79@gmail.com'
                ]),
                'subject' => '[Bins Cafe Contact] ' . $safeSubject,
                'htmlContent' => "
                    <h3>New message from {$safeName}</h3>
                    <p><strong>Email:</strong> {$safeEmail}</p>
                    <p><strong>Subject:</strong> {$safeSubject}</p>
                    <p><strong>Message:</strong><br>{$safeMessage}</p>
                ",
                'replyTo' => new SendTransacEmailRequestReplyTo([
                    'email' => $email,
                    'name' => $fullName === '' ? $firstName : $fullName
                ]),
            ]);

            $brevo->transactionalEmails->sendTransacEmail($emailRequest);
            $this->addFlash('success', "Your message was sent! We'll get back to you within 24 hours. ☕");

        } catch (BrevoApiException | BrevoException $e) {
            $this->addFlash('error', 'Unable to send message right now. Please try again in a moment.');
        }

        return $this->redirectToRoute('app_contact');
    }
}