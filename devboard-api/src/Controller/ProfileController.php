<?php

namespace App\Controller;

use App\DTO\ProfileUpdateDTO;
use App\Service\User\profileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private profileService $profileService,
        private ValidatorInterface $validator
    ) {}

    #[Route('/self', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var \App\Entity\WpUser $user */
        $user = $this->getUser();
        $profile = $this->profileService->getProfile($user);

        $responseData = [
            'status' => 'success',
            'data' => [
                // User data
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isActive' => $user->isActive(),
                // Profile data (if exists)
                'profile' => $profile ? [
                    'firstName' => $profile->getFirstName(),
                    'lastName' => $profile->getLastName(),
                    'bio' => $profile->getBio(),
                    'avatar' => $profile->getAvatar(),
                    'phoneNumber' => $profile->getPhoneNumber(),
                    'location' => $profile->getLocation(),
                    'website' => $profile->getWebsite(),
                    'updatedAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ] : null
            ]
        ];

        return $this->json($responseData);
    }

    #[Route('/self', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Entity\WpUser $user */
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid JSON data'
            ], Response::HTTP_BAD_REQUEST);
        }

        $profileDTO = new ProfileUpdateDTO();
        $profileDTO->firstName = $data['firstName'] ?? null;
        $profileDTO->lastName = $data['lastName'] ?? null;
        $profileDTO->bio = $data['bio'] ?? null;
        $profileDTO->website = $data['website'] ?? null;
        $profileDTO->phoneNumber = $data['phoneNumber'] ?? null;
        $profileDTO->location = $data['location'] ?? null;

        $errors = $this->validator->validate($profileDTO);
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

        $profile = $this->profileService->updateProfile($user, $profileDTO);

        return $this->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                // User data
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isActive' => $user->isActive(),
                // Profile data
                'profile' => [
                    'firstName' => $profile->getFirstName(),
                    'lastName' => $profile->getLastName(),
                    'bio' => $profile->getBio(),
                    'avatar' => $profile->getAvatar(),
                    'phoneNumber' => $profile->getPhoneNumber(),
                    'location' => $profile->getLocation(),
                    'website' => $profile->getWebsite(),
                    'updatedAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ]
            ]
        ]);
    }

    #[Route('/self/avatar', methods: ['POST'])]
    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var \App\Entity\WpUser $user */
        $user = $this->getUser();

        $file = $request->files->get('avatar');
        if (!$file) {
            return $this->json([
                'status' => 'error',
                'message' => 'No file uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Implement file upload logic and validation
        $avatarPath = 'path/to/uploaded/file'; // This should be replaced with actual file upload logic

        $profile = $this->profileService->updateAvatar($user, $avatarPath);

        return $this->json([
            'status' => 'success',
            'message' => 'Avatar updated successfully',
            'data' => [
                // User data
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isActive' => $user->isActive(),
                // Profile data
                'profile' => [
                    'avatar' => $profile->getAvatar(),
                    'updatedAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ]
            ]
        ]);
    }
}