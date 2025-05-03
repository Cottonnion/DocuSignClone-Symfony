<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetDTO
{
    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Password must be at least {{ limit }} characters long',
        max: 50,
        maxMessage: 'Password cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character'
    )]
    public string $password;
} 