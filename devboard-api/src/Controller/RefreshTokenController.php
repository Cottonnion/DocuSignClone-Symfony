<?php

namespace App\Controller;

use App\DTO\LoginDTO;
use App\Service\Auth\TokenRefreshService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\WpUser;

#[Route('/api/token')]
class RefreshTokenController
{
    #[Route('/refresh', name: 'user_refresh_token', methods: ['GET'])]
    public function __construct(
        private TokenRefreshService $tokenRefreshService
    ){

    }
}