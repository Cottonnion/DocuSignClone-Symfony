<?php

namespace App\Controller;

use App\DTO\LoginDTO;
use App\DTO\MFAVerificationDTO;
use App\Service\Auth\LoginService;
use App\Service\Auth\MFAService;
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
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user')]
final class UserAuthController extends AbstractController
{
    public function __construct(
        private LoginService $loginService,
        private MFAService $mfaService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ){}

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

    #[Route('/validate_login', name: 'user_auth_controller', methods: ['POST'])]
    public function loginUser(Request $request): JsonResponse
    {
        try {
            // First check for refresh token in headers
            $refreshToken = $request->headers->get('X-Refresh-Token');
            if ($refreshToken) {
                // Try to validate using refresh token
                $result = $this->loginService->validateLoginWithRefreshToken($refreshToken);
                if ($result['status'] === 'success') {
                    return $this->json($result, Response::HTTP_OK, $this->getSecurityHeaders());
                }
                // If refresh token validation fails, return error
                return $this->json($result, Response::HTTP_UNAUTHORIZED, $this->getSecurityHeaders());
            }

            // If no refresh token, check for email/password in body
            $data = json_decode($request->getContent(), true);
            if (!$data) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ], Response::HTTP_BAD_REQUEST, $this->getSecurityHeaders());
            }

            // Check if this is a MFA verification request
            if (isset($data['code'])) {
                try {
                    $mfaDTO = $this->serializer->deserialize($request->getContent(), MFAVerificationDTO::class, 'json');
                    $errors = $this->validator->validate($mfaDTO);
                    
                    if (count($errors) > 0) {
                        $errorMessages = [];
                        foreach ($errors as $error) {
                            $errorMessages[] = $error->getMessage();
                        }
                        return $this->json([
                            'status' => 'error',
                            'message' => $errorMessages
                        ], Response::HTTP_BAD_REQUEST, $this->getSecurityHeaders());
                    }

                    // Verify MFA code
                    if (!$this->mfaService->verifyMFACode($mfaDTO->email, $mfaDTO->code)) {
                        return $this->json([
                            'status' => 'error',
                            'message' => 'Invalid or expired verification code'
                        ], Response::HTTP_UNAUTHORIZED, $this->getSecurityHeaders());
                    }

                    // If MFA verification successful, generate tokens
                    $result = $this->loginService->handleSuccessfulMFA($mfaDTO->email);
                    return $this->json($result, Response::HTTP_OK, $this->getSecurityHeaders());
                } catch (\Exception $e) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Failed to verify MFA code',
                        'details' => $this->getDebugDetails($e)
                    ], Response::HTTP_INTERNAL_SERVER_ERROR, $this->getSecurityHeaders());
                }
            }

            // Initial login request with email/password
            try {
                $loginDTO = $this->serializer->deserialize($request->getContent(), LoginDTO::class, 'json');
                $errors = $this->validator->validate($loginDTO);
                
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getMessage();
                    }
                    return $this->json([
                        'status' => 'error',
                        'message' => $errorMessages
                    ], Response::HTTP_BAD_REQUEST, $this->getSecurityHeaders());
                }

                // Validate credentials and get user
                $user = $this->loginService->validateCredentials($loginDTO->email, $loginDTO->password);
                if (!$user) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Invalid credentials'
                    ], Response::HTTP_UNAUTHORIZED, $this->getSecurityHeaders());
                }

                // Send MFA code
                $this->mfaService->generateAndSendMFACode($user);
                
                return $this->json([
                    'status' => 'success',
                    'message' => 'Verification code sent to your email',
                    'email' => $loginDTO->email
                ], Response::HTTP_OK, $this->getSecurityHeaders());
            } catch (\Exception $e) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Failed to process login request',
                    'details' => $this->getDebugDetails($e)
                ], Response::HTTP_INTERNAL_SERVER_ERROR, $this->getSecurityHeaders());
            }

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'details' => $this->getDebugDetails($e)
            ], Response::HTTP_INTERNAL_SERVER_ERROR, $this->getSecurityHeaders());
        }
    }

    private function getDebugDetails(\Exception $e): ?array
    {
        if (!$this->getParameter('kernel.debug')) {
            return null;
        }

        return [
            'message' => $e->getMessage(),
            'class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
        ];
    }
}
