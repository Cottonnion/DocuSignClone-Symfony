<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Check;

class PasswordResetRequestDTO
{

    #[Check\NotBlank(message: 'Email is required!')]
    #[Check\Email(message: 'Email is not valid!')]
    public string $email;
}