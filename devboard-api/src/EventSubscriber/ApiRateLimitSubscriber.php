<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private RateLimiterFactory $apiRateLimiter;

    public function __construct(
        RateLimiterFactory $api
    ){
        $this->apiRateLimiter = $api;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only apply to API routes
        if (strpos($request->getPathInfo(), '/api/') !== 0) {
            return;
        }

        try {
            // Use IP address as the limiter key
            $limiter = $this->apiRateLimiter->create($request->getClientIp());
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $response = new JsonResponse([
                    'status' => 'error',
                    'type'  => 'rate_limited',
                    'code' => 429,
                    'message' => 'Too many requests, slow down!',
                    'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
                ], Response::HTTP_TOO_MANY_REQUESTS);

                $response->headers->add([
                    'Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()
                ]);

                $event->setResponse($response);
            }
        } catch (\Exception $e) {
            // TODO: Log the error
        }
    }
}