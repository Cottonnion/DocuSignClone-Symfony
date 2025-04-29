<?php

namespace App\Service\User;

use App\DTO\ProfileUpdateDTO;
use App\Entity\Profile;
use App\Entity\WpUser;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

class profileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProfileRepository $profileRepository
    ) {}

    public function getProfile(WpUser $user): ?Profile
    {
        return $user->getProfile();
    }

public function updateProfile(WpUser $user, ProfileUpdateDTO $profileData): Profile
{
    $profile = $this->profileRepository->findByUserId($user->getId());
    
    if (!$profile) {
        $profile = new Profile();
        $profile->setUser($user);
    }

    if ($profileData->firstName !== null) {
        $profile->setFirstName($profileData->firstName);
    }
    if ($profileData->lastName !== null) {
        $profile->setLastName($profileData->lastName);
    }
    if ($profileData->bio !== null) {
        $profile->setBio($profileData->bio);
    }
    if ($profileData->website !== null) {
        $profile->setWebsite($profileData->website);
    }
    if ($profileData->phoneNumber !== null) {
        $profile->setPhoneNumber($profileData->phoneNumber);
    }
    if ($profileData->location !== null) {
        $profile->setLocation($profileData->location);
    }

    $profile->setUpdatedAt(new \DateTimeImmutable());
    
    $this->entityManager->persist($profile);
    $this->entityManager->flush();

    return $profile;
}

    public function updateAvatar(WpUser $user, string $avatarPath): Profile
    {
        $profile = $user->getProfile();
        
        if (!$profile) {
            $profile = new Profile();
            $profile->setUser($user);
        }

        $profile->setAvatar($avatarPath);
        $profile->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }
}