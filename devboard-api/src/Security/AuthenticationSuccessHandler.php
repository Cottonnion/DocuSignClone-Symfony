<?php

namespace App\Security;

use App\Service\Auth\TokenRefreshService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler as BaseHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Entity\WpUser;

class AuthenticationSuccessHandler extends BaseHandler
{
    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        private TokenRefreshService $tokenRefreshService
    ) {
        parent::__construct($jwtManager, $dispatcher);
    }

    public function handleAuthenticationSuccess(UserInterface $user, $jwt = null): Response
    {
        $response = parent::handleAuthenticationSuccess($user, $jwt);
        
        if (!$user instanceof WpUser) {
            return $response;
        }

        // Generate both tokens using the TokenRefreshService
        $tokens = $this->tokenRefreshService->generateTokens($user);
        
        $data = [
            'status' => 'success',
            'tokens' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_in' => $tokens['expires_in']
            ],
            'user' => [
                'email' => $user->getEmail(),
                'username' => $user->getUsername()
            ]
        ];

        $response->setData($data);
        
        return $response;
    }
}