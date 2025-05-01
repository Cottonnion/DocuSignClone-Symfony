<?php

namespace App\Service\User;

use App\DTO\RegisterDTO;
use App\Entity\WpUser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegisterService
{
    public function __construct(
        private EntityManagerInterface $entityManagerInterface,
        private UserPasswordHasherInterface $userPasswordHasherInterface,
        private UserLoggerService $userLogger
    ) {}

    public function register(RegisterDTO $userData): array
    {
        try {
            $newUser = new WpUser();
            $newUser->setEmail($userData->email);
            $newUser->setUsername($userData->username);

            // Hash password
            $hashedPassword = $this->userPasswordHasherInterface->hashPassword($newUser, $userData->password);
            $newUser->setPassword($hashedPassword);

            // Default Role
            $newUser->setRoles(['ROLE_USER']);

            // Save to DB
            $this->entityManagerInterface->persist($newUser);
            $this->entityManagerInterface->flush();

            // Log successful registration
            $this->userLogger->logRegistrationAttempt(
                $userData->email,
                $userData->username,
                true
            );

            return [
                'status'    =>  'success',
                'message'   =>  'User registered succesfully!',
                'user'      =>  [
                    'email' =>  $newUser->getEmail(),
                    'username'=> $newUser->getUsername(),
                ]
            ];
        } catch (UniqueConstraintViolationException $e) {
            // Check which constraint was violated
            if (strpos($e->getMessage(), 'uniq_username') !== false) {
                return [
                    'status' => 'error',
                    'type'  =>  'not_unique_username',
                    'message' => 'Username is already in use.'
                ];
            } elseif (strpos($e->getMessage(), 'uniq_email') !== false) {
                return [
                    'status' => 'error',
                    'type'  => 'not_unique_email',
                    'message' => 'Email is already in use.'
                ];
            }
            
            // Generic error for other constraint violations
            return [
                'status' => 'error',
                'message' => 'A unique constraint violation occurred.'
            ];
        } catch (\Exception $e) {
            // Log failed registration
            $this->userLogger->logRegistrationAttempt(
                $userData->email,
                $userData->username,
                false,
                $e->getMessage()
            );
            
            return [
                'status' => 'error',
                'message' => 'An error occurred during registration.'
            ];
        }
    }
}