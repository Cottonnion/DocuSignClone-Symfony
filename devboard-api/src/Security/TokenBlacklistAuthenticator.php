<?php

namespace App\Security;

use App\Service\Auth\TokenBlacklistService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TokenBlacklistAuthenticator extends JWTAuthenticator
{
    public function __construct(
        private TokenBlacklistService $tokenBlacklistService
    ) {
        parent::__construct();
    }

    public function supports(Request $request): ?bool
    {
        return parent::supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->getTokenExtractor()->extract($request);
        
        if ($token && $this->tokenBlacklistService->isTokenBlacklisted($token)) {
            throw new AuthenticationException('Token has been blacklisted');
        }
        
        return parent::authenticate($request);
    }
} 