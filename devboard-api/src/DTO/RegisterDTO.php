<?php

namespace App\DTO;

use App\Validator\uniqueEmail;
use Symfony\Component\Validator\Constraints as Check;

class RegisterDTO
{

    #[Check\NotBlank(message: 'Email is requried')]
    #[Check\Email(message: 'Invalid Email Address')]
    #[uniqueEmail]
    public string $email;

    #[Check\NotBlank]
    #[Check\Length(min:8, minMessage: 'Password must be atleast 8 characters long!')]
    #[Check\Regex(    
        pattern: '/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/',
        message: 'Password must contain at least one letter and one number'
    )]
    public string $password;

    // #[Check\NotBlank('Password is required')]
    #[Check\Length(min:4, minMessage: 'Username must be atleast 4 characters long!')]
    public string $username;
}