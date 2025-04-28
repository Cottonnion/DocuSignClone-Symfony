<?php

namespace App\Service\Auth;

use App\Service\User\userLoggerService;
use App\Repository\WpUserRepository;
use App\DTO\LoginDTO;
use App\Entity\WpUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class loginService
{
    private int $maxAttempts;
    private int $lockoutMinutes;
    private array $errorMessages;

    public function __construct(
        private EntityManagerInterface $entityManagerInterface,
        private UserPasswordHasherInterface $userPasswordHasherInterface,
        private userLoggerService $userLogger,
        private WpUserRepository $userRepo,
        private JWTTokenManagerInterface $jwtManager,
        ParameterBagInterface $params
    ) {
        // Set default values in case parameters are missing
        $this->maxAttempts = $params->get('login.max_attempts', 5);
        $this->lockoutMinutes = $params->get('login.lockout_minutes', 15);
        
        // Set default error messages
        $defaultErrorMessages = [
            'invalid_credentials' => 'Invalid email or password.',
            'account_locked' => 'Account is locked. Please try again later.',
            'account_inactive' => 'Account is inactive. Please contact support.',
            'generic_error' => 'An error occurred during login.'
        ];
        
        // Try to get configured messages, fall back to defaults if not found
        $this->errorMessages = $params->get('login.error_messages', $defaultErrorMessages);
    }

    public function loginUser(LoginDTO $loginData): array
    {
        try {
            // Find user by email
            $user = $this->userRepo->findByEmail($loginData->email);

            // Check if user exists
            if (!$user) {
                $this->userLogger->logLoginAttempt($loginData->email, false, 'User not found');
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
            if (!$this->userPasswordHasherInterface->isPasswordValid($user, $loginData->password)) {
                $user->increaseLoginAttempts();
                $this->entityManagerInterface->persist($user);
                $this->entityManagerInterface->flush();

                if ($user->getFailedLoginAttempts() >= $this->maxAttempts) {
                    $user->setLockoutUntil((new \DateTimeImmutable())->modify("+{$this->lockoutMinutes} minutes"));
                    $user->setFailedLoginAttempts(0); // Reset counter after lockout
                    $this->entityManagerInterface->persist($user);
                    $this->entityManagerInterface->flush();
                }

                $this->userLogger->logLoginAttempt($user->getEmail(), false, 'Invalid password');
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
            $this->entityManagerInterface->persist($user);
            $this->entityManagerInterface->flush();

            // Generate JWT token
            $token = $this->jwtManager->create($user);

            // Log successful login
            $this->userLogger->logLoginAttempt($user->getEmail(), true);

            return [
                'status' => 'success',
                'token' => $token,
                'user' => [
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername()
                ],
                'headers' => $this->getSecurityHeaders()
            ];

        } catch (\Exception $e) {
            // Log failed login attempt with the actual error message
            $this->userLogger->logLoginAttempt($loginData->email, false, $e->getMessage());
            
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