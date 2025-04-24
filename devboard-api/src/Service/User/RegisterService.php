<?php

namespace App\Service\User;

class RegisterService
{

    public function register(array $userData): array
    {
        return [
            'message'   => 'maan, wtf is symfony',
            'user'      => $userData
        ];
    }
}