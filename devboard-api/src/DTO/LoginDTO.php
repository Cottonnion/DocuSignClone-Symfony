<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Check;

class LoginDTO
{

    #[Check\NotBlank(message: 'Email is required')]
    #[Check\Email(message: 'Invalid Email Address')]
    public string $email;

    #[Check\NotBlank(message: 'Password is required')]
    #[Check\Length(
        min: 8,
        minMessage: 'Password must be at least {{ limit }} characters long',
        max: 50,
        maxMessage: 'Password cannot be longer than {{ limit }} characters'
    )]
    #[Check\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, one number and one special character'
    )]
    public string $password;
}
