<?php

namespace App\Controller;

use App\Service\Auth\TokenRefreshService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
/**
 * Handles JWT token refresh operations
 */
#[Route('/api/token')]
class RefreshTokenController extends AbstractController
{
    public function __construct(
        private TokenRefreshService $tokenRefreshService
    ) {}

    /**
     * Generates a new access token using a refresh token
     * 
     * @param Request $request Must contain X-Refresh-Token header
     * @return JsonResponse New access token or error message
     */
    #[Route('/refresh', name: 'user_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $refreshToken = $request->headers->get('X-Refresh-Token');
        
        if (!$refreshToken) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Refresh token is required'
            ], 400);
        }

        $result = $this->tokenRefreshService->refreshAccessToken($refreshToken);
        
        if (!$result) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }

        return new JsonResponse([
            'status' => 'success',
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in']
        ]);
    }
}