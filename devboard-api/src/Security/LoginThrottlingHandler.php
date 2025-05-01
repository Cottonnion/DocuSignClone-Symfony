<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

class LoginThrottlingHandler
{
    public function handle(Request $request, TooManyLoginAttemptsAuthenticationException $exception): Response
    {
        $message = $exception->getMessage();
        // Extract the retry time from the message
        preg_match('/try again in (\d+) minutes/', $message, $matches);
        $retryAfter = isset($matches[1]) ? ((int)$matches[1] * 60)/60 : '15'; // Default to 15 minutes if not found
        
        $response = new JsonResponse([
            'code' => '429',
            'type' => 'rate_limited',
            'message' => "This user exceeded the requests rate limit",
            'retry_after' => $retryAfter . ' minute',
            'remaining_attempts' => 0
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->add([
            'Retry-After' => $retryAfter . ' minute'
        ]);

        return $response;
    }
} 