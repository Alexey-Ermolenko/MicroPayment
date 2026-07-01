<?php

namespace App\EventListener;

use App\Exception\DomainException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

#[AsEventListener]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api') || str_starts_with($path, '/api/doc')) {
            return;
        }

        $e = $event->getThrowable();
        $validation = $this->extractValidationException($e);

        [$status, $body] = match (true) {
            $e instanceof DomainException => [$e->statusCode(), ['error' => $e->getMessage()]],
            null !== $validation => [422, $this->violations($validation)],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), ['error' => $e->getMessage()]],
            default => [500, ['error' => 'Internal server error.']],
        };

        $event->setResponse(new JsonResponse($body, $status));
    }

    private function extractValidationException(Throwable $e): ?ValidationFailedException
    {
        if ($e instanceof ValidationFailedException) {
            return $e;
        }

        $previous = $e->getPrevious();

        return $previous instanceof ValidationFailedException ? $previous : null;
    }

    private function violations(ValidationFailedException $e): array
    {
        $errors = [];
        foreach ($e->getViolations() as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return ['error' => 'Validation failed.', 'violations' => $errors];
    }
}
