<?php

namespace App\Controller;

use App\DTO\RegisterDTO;
use App\Service\User\RegisterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[Route('/api/user')]
final class userRegisterController extends AbstractController
{

    public function __construct(
        private RegisterService $registrationService,
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


    #[Route('/create', name: 'app_user_register', methods: ['POST'])]
    public function registerUser(
        #[MapRequestPayload] RegisterDTO $registerDTO
    ): Response
    {
        try {
            $errors = $this->validator->validate($registerDTO);

            if(count($errors) > 0){
                $errorMessages = [];
                foreach ($errors as $err) {
                    $errorMessages[] = $err->getMessage();
                }
                return $this->json([
                    'status' => 'error',
                    'message' => $errorMessages
                ], 
                Response::HTTP_BAD_REQUEST,
                $this->getSecurityHeaders());
            }

            $result = $this->registrationService->register($registerDTO);

            // Check if the result is an error response
            if ($result['status'] === 'error') {
                return $this->json($result, 
                Response::HTTP_BAD_REQUEST,
                $this->getSecurityHeaders());
            }

            // Return success response with 201 Created status
            return $this->json($result, 
            Response::HTTP_CREATED,
            $this->getSecurityHeaders());

        } catch (ValidationFailedException $e) {
            $errorMessages = [];
            foreach ($e->getViolations() as $violation) {
                $errorMessages[] = $violation->getMessage();
            }
            return $this->json([
                'status' => 'error',
                'message' => $errorMessages
            ], 
            Response::HTTP_BAD_REQUEST,
            $this->getSecurityHeaders());
        }
    }
}
