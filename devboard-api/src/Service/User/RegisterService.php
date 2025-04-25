<?php

namespace App\Service\User;

use App\DTO\RegisterDTO;
use App\Entity\WpUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class registerService
{
    public function __construct(
        private EntityManagerInterface $entityManagerInterface,
        private UserPasswordHasherInterface $userPasswordHasherInterface
    ) {}

    public function register(RegisterDTO $userData): array
    {

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

        return [
            'status'    =>  'success',
            'message'   =>  'User registered succesfully!',
            'user'      =>  [
                'email' =>  $newUser->getEmail(),
                'username'=> $newUser->getUsername(),
            ]
        ];
    }
}