<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExceptionListener implements EventSubscriberInterface
{
    private bool $debug;

    public function __construct(bool $debug = true) // Set debug to true by default
    {
        $this->debug = $debug;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        
        // Only handle API requests
        if (strpos($request->getPathInfo(), '/api/') !== 0) {
            return;
        }
        
        $statusCode = 500;
        $message = 'Internal Server Error';
        $details = null;
        
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }
        
        // Handle validation errors
        if ($exception instanceof ValidationFailedException) {
            $statusCode = 400;
            $message = $this->formatValidationErrors($exception->getViolations());
        }

        // Always include detailed error information in development
        $details = [
            'message' => $exception->getMessage(),
            'class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 10) // First 10 lines of stack trace
        ];
        
        $response = new JsonResponse([
            'status' => 'error',
            'message' => $message,
            'details' => $details
        ], $statusCode);
        
        $event->setResponse($response);
    }
    
    private function formatValidationErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = $violation->getMessage();
        }
        return $errors;
    }
} 