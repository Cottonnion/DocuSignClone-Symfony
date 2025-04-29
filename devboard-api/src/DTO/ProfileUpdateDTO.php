<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Check;

class ProfileUpdateDTO
{
    #[Check\Length(max: 255, maxMessage: 'username toolong', min: 3, minMessage: 'username too short')]
    public ?string $firstName = null;

    #[Check\Length(max: 255)]
    public ?string $lastName = null;

    #[Check\Length(max: 1000)]
    public ?string $bio = null;

    #[Check\Url]
    #[Check\Length(max: 255)]
    public ?string $website = null;

    #[Check\Length(max: 20)]
    #[Check\Regex(
        pattern: '/^[+]?[0-9]{10,15}$/',
        message: 'Invalid phone number format'
    )]
    public ?string $phoneNumber = null;

    #[Check\Length(max: 255)]
    public ?string $location = null;
}