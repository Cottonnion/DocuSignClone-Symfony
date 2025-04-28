<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\WpUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WP_Http_Cookie;

#[Route('api/user')]
final class userProfileController extends AbstractController
{

    private function getSecurityHeaders(): array
    {
        return [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'self'",
            'Referrer-Policy' => 'no-referrer',
        ];
    }

    #[Route('/self', name: 'app_user_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProfile(#[CurrentUser] WpUser $user): JsonResponse
    {
        return $this->json([
            'status'    => 'success',
            'user'      => [
                'email'         =>  $user->getEmail(),
                'username'      =>  $user->getUsername(),
                'lastLoginAt'   =>  $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'createdAt'     =>  $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isActive'      =>  $user->isActive() 
            ]
            ], Response::HTTP_OK, $this->getSecurityHeaders());
    }
}
