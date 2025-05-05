<?php

namespace App\Controller;

use App\DTO\PasswordResetRequestDTO;
use App\DTO\PasswordResetDTO;
use App\Service\User\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user')]
final class PasswordResetController extends AbstractController
{
    public function __construct(
        private PasswordResetService $passwordResetService,
        private ValidatorInterface $validator
    ) {}

    #[Route('/request-password-reset', name: 'request_password_reset', methods: ['POST'])]
    public function requestReset(
        #[MapRequestPayload] PasswordResetRequestDTO $resetRequest
    ): JsonResponse
    {
        $this->passwordResetService->sendResetEmail($resetRequest->email);
        
        return $this->json([
            'status' => 'success',
            'message' => 'If an account exists with this email, a password reset link has been sent.'
        ], Response::HTTP_OK);
    }

    #[Route('/reset-password/{token}', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(
        string $token,
        #[MapRequestPayload] PasswordResetDTO $resetDTO
    ): JsonResponse
    {
        $errors = $this->validator->validate($resetDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'status' => 'error',
                'message' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->passwordResetService->resetPassword($token, $resetDTO->password);
        
        return $this->json($result, $result['status'] === 'success' ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }
} 