<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;

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
        
        // Handle JWT authentication exceptions
        if ($exception instanceof MissingTokenException) {
            $statusCode = 401;
            $message = 'No authentication token provided';
        } elseif ($exception instanceof ExpiredTokenException) {
            $statusCode = 401;
            $message = 'Authentication token has expired';
        } elseif ($exception instanceof InvalidTokenException) {
            $statusCode = 401;
            $message = 'Invalid authentication token';
        } elseif ($exception instanceof JWTDecodeFailureException) {
            $statusCode = 401;
            $message = 'Failed to decode authentication token';
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }
        
        // Handle validation errors
        if ($exception instanceof ValidationFailedException) {
            $statusCode = 400;
            $message = $this->formatValidationErrors($exception->getViolations());
        }

        // Only include detailed error information in development
        if ($this->debug) {
            $details = [
                'message' => $exception->getMessage(),
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 10) // First 10 lines of stack trace
            ];
        }
        
        $response = new JsonResponse([
            'status' => 'error',
            'message' => $message,
            'code' => $statusCode,
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