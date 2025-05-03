<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationFailureHandler as BaseHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AuthenticationFailureHandler extends BaseHandler
{
    public function __construct(
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct($dispatcher);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = 'Authentication failed';
        
        // Customize error message based on the exception type
        if (str_contains($exception->getMessage(), 'JWT Token not found')) {
            $message = 'No authentication token provided';
        } elseif (str_contains($exception->getMessage(), 'Expired JWT Token')) {
            $message = 'Authentication token has expired';
        } elseif (str_contains($exception->getMessage(), 'Invalid JWT Token')) {
            $message = 'Invalid authentication token';
        }

        return new JsonResponse([
            'status' => 'error',
            'message' => $message,
            'code' => 401
        ], 401);
    }
} 