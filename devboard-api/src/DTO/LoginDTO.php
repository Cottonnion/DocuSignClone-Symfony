<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as check;

class LoginDTO
{

    #[Check\NotBlank(message: 'Email is required')]
    #[Check\Email(message: 'Invalid Email Address')]
    public string $email;

    #[Check\NotBlank(message: 'Password is requred')]
    #[Check\Length(min:8, minMessage: 'Password must be atleast 8 characters long')]
    #[Check\Regex(    
        pattern: '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/',
        message: 'Password must contain at least one letter and one number'
    )]
    public string $password;
}
