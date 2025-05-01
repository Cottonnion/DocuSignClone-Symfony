<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Security\Http\RateLimiter\LoginRateLimiter;

class RateLimitResponseHandler
{
    public function handle(Request $request, RateLimit $rateLimit): Response
    {
        $retryAfter = $rateLimit->getRetryAfter()->format('%i minutes and %s seconds');
        
        $response = new JsonResponse([
            'status' => 'error',
            'type' => 'rate_limited',
            'message' => "Too many login attempts. Please try again in {$retryAfter}",
            'retry_after' => $rateLimit->getRetryAfter()->format('%s'),
            'remaining_attempts' => $rateLimit->getRemainingTokens()
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->add([
            'X-RateLimit-Limit' => $rateLimit->getLimit(),
            'X-RateLimit-Remaining' => $rateLimit->getRemainingTokens(),
            'X-RateLimit-Reset' => $rateLimit->getRetryAfter()->getTimestamp(),
            'Retry-After' => $rateLimit->getRetryAfter()->format('%s')
        ]);

        return $response;
    }
} 