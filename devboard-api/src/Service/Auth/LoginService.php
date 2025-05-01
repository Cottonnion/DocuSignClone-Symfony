<?php

namespace App\Service\Auth;

use App\Service\User\UserLoggerService;
use App\Repository\WpUserRepository;
use App\DTO\LoginDTO;
use App\Entity\WpUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use App\Security\IpRateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Auth\TokenRefreshService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LoginService
{
    private int $maxAttempts;
    private int $lockoutMinutes;
    private array $errorMessages;

    public function __construct(
        private WpUserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private UserLoggerService $userLogger,
        private IpRateLimiterService $ipRateLimiter,
        private EntityManagerInterface $entityManager,
        private TokenRefreshService $tokenRefreshService,
        private ParameterBagInterface $params
    ) {
        // Set default values in case parameters are missing
        $this->maxAttempts = $this->params->get('app.login.max_attempts');
        $this->lockoutMinutes = $this->params->get('login.lockout_minutes', 15);
        
        // Set default error messages
        $defaultErrorMessages = [
            'invalid_credentials' => 'Invalid email or password.',
            'account_locked' => 'Account is locked. Please try again later.',
            'account_inactive' => 'Account is inactive. Please contact support.',
            'generic_error' => 'An error occurred during login.'
        ];
        
        // Try to get configured messages, fall back to defaults if not found
        $this->errorMessages = $this->params->get('login.error_messages', $defaultErrorMessages);
    }

    public function checkIpRateLimit(string $clientIp): array
    {
        return $this->ipRateLimiter->isIpAllowed($clientIp);
    }

    public function loginUser(LoginDTO $loginData, string $clientIp): array
    {
        // Check IP rate limiting first
        $ipCheck = $this->ipRateLimiter->isIpAllowed($clientIp);
        if (!$ipCheck['allowed']) {
            return [
                'status' => 'error',
                'message' => $ipCheck['message'],
                'retry_after' => $ipCheck['retry_after'],
                'headers' => array_merge(
                    $this->getSecurityHeaders(),
                    ['Retry-After' => $ipCheck['retry_after']]
                )
            ];
        }
        
        try {
            // Find user by email
            $user = $this->userRepository->findByEmail($loginData->email);

            // Check if user exists
            if (!$user) {
                $this->userLogger->logLoginAttempt($loginData->email, false, 'User not found');
                
                // Mark this as a failed attempt for rate limiting
                $this->ipRateLimiter->isIpAllowed($clientIp);
                
                return [
                    'status' => 'error',
                    'message' => $this->errorMessages['invalid_credentials'],
                    'headers' => $this->getSecurityHeaders()
                ];
            }

            // Check if user is active
            if (!$user->isActive()) {
                $this->userLogger->logLoginAttempt($user->getEmail(), false, 'Account inactive');
                return [
                    'status' => 'error',
                    'message' => $this->errorMessages['account_inactive'],
                    'headers' => $this->getSecurityHeaders()
                ];
            }

            // Verify if account is locked
            if ($user->isLockedOut()) {
                $this->userLogger->logLoginAttempt($user->getEmail(), false, 'Account locked');
                return [
                    'status' => 'error',
                    'message' => $this->errorMessages['account_locked'],
                    'headers' => $this->getSecurityHeaders()
                ];
            }

            // Verify password
            if (!$this->passwordHasher->isPasswordValid($user, $loginData->password)) {
                $user->increaseLoginAttempts();
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                if ($user->getFailedLoginAttempts() >= $this->maxAttempts) {
                    $user->setLockoutUntil((new \DateTimeImmutable())->modify("+{$this->lockoutMinutes} minutes"));
                    $user->setFailedLoginAttempts(0); // Reset counter after lockout
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                $this->userLogger->logLoginAttempt($user->getEmail(), false, 'Invalid password');
                
                // Mark this as a failed attempt for rate limiting
                $ipCheck = $this->ipRateLimiter->isIpAllowed($clientIp);
                if (!$ipCheck['allowed']) {
                    return [
                        'status' => 'error',
                        'message' => $ipCheck['message'],
                        'retry_after' => $ipCheck['retry_after'],
                        'headers' => array_merge(
                            $this->getSecurityHeaders(),
                            ['Retry-After' => $ipCheck['retry_after']]
                        )
                    ];
                }
                
                return [
                    'status' => 'error',
                    'message' => $this->errorMessages['invalid_credentials'],
                    'headers' => $this->getSecurityHeaders()
                ];
            }

            // Update last login time
            $user->setLastLoginAt(new \DateTimeImmutable());
            
            // Reset failed attempts and lockout
            $user->setFailedLoginAttempts(0);
            $user->setLockoutUntil(null);

            // Persist changes immediately after updating lastLoginAt
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Generate both access and refresh tokens
            $tokens = $this->tokenRefreshService->generateTokens($user);

            // Log successful login
            $this->userLogger->logLoginAttempt($user->getEmail(), true);

            return [
                'status'        =>  'success',
                'access_token'  =>  $tokens['access_token'],
                'refresh_token' =>  $tokens['refresh_token'],
                'user' => [
                    'email'     => $user->getEmail(),
                    'username'  => $user->getUsername()
                ],
                'headers' => $this->getSecurityHeaders()
            ];

        } catch (\Exception $e) {
            // Log failed login attempt with the actual error message
            $this->userLogger->logLoginAttempt($loginData->email, false, $e->getMessage());
            
            // Mark this as a failed attempt for rate limiting
            $this->ipRateLimiter->isIpAllowed($clientIp);
            
            // Return a generic error message to the client
            return [
                'status' => 'error',
                'message' => $this->errorMessages['generic_error'],
                'headers' => $this->getSecurityHeaders()
            ];
        }
    }

    private function getSecurityHeaders(): array
    {
        return [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'self'",
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}