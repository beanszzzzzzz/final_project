<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\ValidationFailedException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Only alter responses for API requests
        $isApi = str_starts_with($request->getPathInfo(), '/api') || str_contains($request->headers->get('accept', ''), 'application/json');
        if (!$isApi) {
            return;
        }

        $e = $event->getThrowable();

        $status = 500;
        $message = 'An unexpected error occurred.';
        $errors = null;

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: JsonResponse::$statusTexts[$status] ?? 'Error';
        }

        if ($e instanceof AccessDeniedException) {
            $status = 403;
            $message = $e->getMessage() ?: 'Access denied.';
        }

        if ($e instanceof AuthenticationException) {
            $status = 401;
            $message = $e->getMessage() ?: 'Authentication required.';
        }

        // Serializer validation exception
        if ($e instanceof ValidationFailedException) {
            $status = 422;
            $message = 'Validation failed.';
            $violations = $e->getViolations();
            if ($violations instanceof ConstraintViolationListInterface) {
                $errors = $this->formatViolations($violations);
            }
        }

        // Generic constraint violations carried in exception
        if (method_exists($e, 'getViolations')) {
            $violations = $e->getViolations();
            if ($violations instanceof ConstraintViolationListInterface) {
                $status = 422;
                $message = 'Validation failed.';
                $errors = $this->formatViolations($violations);
            }
        }

        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $response = new JsonResponse($payload, $status);
        $event->setResponse($response);
    }

    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'code' => $violation->getCode(),
            ];
        }

        return $errors;
    }
}
