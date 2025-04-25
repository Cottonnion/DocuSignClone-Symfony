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
        
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }
        
        // Handle validation errors
        if ($exception instanceof ValidationFailedException) {
            $statusCode = 400;
            $message = $this->formatValidationErrors($exception->getViolations());
        }
        
        $response = new JsonResponse([
            'status' => 'error',
            'message' => $message,
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