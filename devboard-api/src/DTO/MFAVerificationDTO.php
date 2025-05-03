<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class MFAVerificationDTO
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    public string $email;

    #[Assert\NotBlank(message: 'Verification code is required')]
    #[Assert\Length(
        min: 6,
        max: 6,
        exactMessage: 'Verification code must be exactly 6 digits'
    )]
    #[Assert\Regex(
        pattern: '/^\d{6}$/',
        message: 'Verification code must contain only digits'
    )]
    public string $code;
} 