<?php

namespace App\Controller;

use App\DTO\LoginDTO;
use App\Service\Auth\LoginService;
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

#[Route('/api/user')]
final class UserAuthController extends AbstractController
{
    public function __construct(
        private LoginService $loginService,
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
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Invalid JSON data'
                ], Response::HTTP_BAD_REQUEST, $this->getSecurityHeaders());
            }

            $loginDTO = new LoginDTO();
            $loginDTO->email = $data['email'] ?? '';
            $loginDTO->password = $data['password'] ?? '';

            $errors = $this->validator->validate($loginDTO);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $err) {
                    $errorMessages[] = $err->getMessage();
                }
            
                return $this->json([
                    'status' => 'error',
                    'message' => $errorMessages
                ], Response::HTTP_BAD_REQUEST, $this->getSecurityHeaders());
            }

            // Get client IP with dallbacks
            $clientIp = $request->headers->get('CF-Connecting-IP') 
            ?? $request->headers->get('X-Forwarded-For') 
            ?? $request->getClientIp();

            $result = $this->loginService->loginUser($loginDTO, $clientIp);
            
            // Extract headers from result
            $headers = $result['headers'] ?? $this->getSecurityHeaders();
            unset($result['headers']); // Remove headers from response body

            // Check if the result is an error response
            if ($result['status'] === 'error') {
                return $this->json(
                    $result,
                    Response::HTTP_UNAUTHORIZED,
                    $headers
                );
            }

            // Return success response with 200
            return $this->json(
                $result,
                Response::HTTP_OK,
                $headers
            );

        } catch (ValidationFailedException $e) {
            $errorMessages = [];
            foreach ($e->getViolations() as $violation) {
                $errorMessages[] = $violation->getMessage();
            }
            return $this->json([
                'status' => 'error',
                'message' => $errorMessages
            ], Response::HTTP_BAD_REQUEST, $this->getSecurityHeaders());
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'An error occurred during login'
            ], Response::HTTP_INTERNAL_SERVER_ERROR, $this->getSecurityHeaders());
        }
    }
}
