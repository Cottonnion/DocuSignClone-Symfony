<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private RateLimiterFactory $authenticatedApiLimiter;
    private RateLimiterFactory $unauthenticatedApiLimiter;
    private Security $security;

    public function __construct(
        RateLimiterFactory $authenticatedApiLimiter,
        RateLimiterFactory $unauthenticatedApiLimiter,
        Security $security
    ){
        $this->authenticatedApiLimiter = $authenticatedApiLimiter;
        $this->unauthenticatedApiLimiter = $unauthenticatedApiLimiter;
        $this->security = $security;
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
            // Get the user if authenticated
            $user = $this->security->getUser();
            
            // Choose appropriate rate limiter based on authentication
            $limiterFactory = $user ? $this->authenticatedApiLimiter : $this->unauthenticatedApiLimiter;
            
            // Use user identifier if authenticated, otherwise fallback to IP
            $limiterKey = $user ? $user->getUserIdentifier() : $request->getClientIp();
            
            // Create a unique key per endpoint
            $endpointKey = $request->getMethod() . ':' . $request->getPathInfo();
            $limiterKey = $limiterKey . ':' . $endpointKey;
            
            $limiter = $limiterFactory->create($limiterKey);
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $response = new JsonResponse([
                    'status' => 'error',
                    'type'  => 'rate_limited',
                    'code' => 429,
                    'message' => 'Too many requests, slow down!',
                    'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
                    'endpoint' => $endpointKey
                ], Response::HTTP_TOO_MANY_REQUESTS);

                $response->headers->add([
                    'Retry-After' => $limit->getRetryAfter()->getTimestamp() - time(),
                    'X-RateLimit-Limit' => $limit->getLimit(),
                    'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
                    'X-RateLimit-Reset' => $limit->getRetryAfter()->getTimestamp()
                ]);

                $event->setResponse($response);
            }
        } catch (\Exception $e) {
            // Log the error but don't block the request
            error_log('Rate limiter error: ' . $e->getMessage());
        }
    }
}