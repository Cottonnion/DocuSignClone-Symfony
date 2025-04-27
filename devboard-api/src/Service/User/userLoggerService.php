<?php

namespace App\Service\User;

use Psr\Log\LoggerInterface;

class userLoggerService
{

    public function __construct(
        private LoggerInterface $userLogger
    ){}

    public function logRegistrationAttempt(string $email, string $username, bool $success, ?string $error = null): void
    {
        $context = [
            'email' => $email,
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        if($success) {
            $this->userLogger->info('User registration successful', $context);
        } else {
            $this->userLogger->warning('User registration failed', array_merge($context, ['error'=> $error]));
        }
    }

    public function logLoginAttempt(string $email, bool $success, ?string $error = null): void
    {
        $context = [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ];

        if($success) {
            $this->userLogger->info('User login successful', $context);
        } else {
            $this->userLogger->warning('User login failed', array_merge($context, ['error'=> $error]));
        }
    }
}