<?php

namespace App\Security;

use App\Service\Auth\LoginService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class LoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private LoginService $loginService
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/user/validate_login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            throw new CustomUserMessageAuthenticationException('Invalid JSON data');
        }

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            throw new CustomUserMessageAuthenticationException('Email and password are required');
        }

        // Get client IP with fallbacks
        $clientIp = $request->headers->get('CF-Connecting-IP') 
            ?? $request->headers->get('X-Forwarded-For') 
            ?? $request->getClientIp();

        // Check rate limiting
        $ipCheck = $this->loginService->checkIpRateLimit($clientIp);
        if (!$ipCheck['allowed']) {
            throw new CustomUserMessageAuthenticationException($ipCheck['message']);
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(
            json_encode([
                'status' => 'error',
                'message' => $exception->getMessage()
            ]),
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/json']
        );
    }
} 