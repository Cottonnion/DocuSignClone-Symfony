<?php

namespace App\Controller;

use App\DTO\RegisterDTO;
use App\Service\User\registerService;
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
        private registerService $registrationService,
        private ValidatorInterface $validator  
    ){}


    #[Route('/register', name: 'app_user_register', methods: ['POST'])]
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
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->registrationService->register($registerDTO);

            return $this->json($result, Response::HTTP_CREATED);
        } catch (ValidationFailedException $e) {
            $errorMessages = [];
            foreach ($e->getViolations() as $violation) {
                $errorMessages[] = $violation->getMessage();
            }
            return $this->json([
                'status' => 'error',
                'message' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
