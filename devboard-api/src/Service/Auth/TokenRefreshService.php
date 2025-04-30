<?php

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\WpUser;
use App\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class TokenRefreshService
{
    
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jWTTokenManager
    ){}

    public function generateTokens( WpUser $user): array
    {
        $this->deactivateExistingTokens($user);

        $accessToken = $this->jWTTokenManager->create($user);

        $refreshToken = bin2hex(random_bytes(32));

        $refreshTokenEntity = new RefreshToken();
        $refreshTokenEntity->setUser($user);
        $refreshTokenEntity->setRefreshToken(hash('sha256', $refreshToken));
        $refreshTokenEntity->setExpiresAt(new DateTimeImmutable('+14 days'));
        $refreshTokenEntity->setIsActive(true);
        $refreshTokenEntity->setCreatedAt(new DateTimeImmutable()); // Add this line


        $this->entityManager->persist($refreshTokenEntity);
        $this->entityManager->flush();

        return [
            'success'       =>  true,
            'access_token'  =>  $accessToken,
            'refresh_token' =>  $refreshToken,
            'expires_in'    =>  900
        ];
    }

    public function refreshAccessToken(string $refreshToken):? array
    {
        $hashedToken = hash('sha256', $refreshToken);
        $tokenEntity = $this->refreshTokenRepository->findOneBy([
            'refresh_token' =>  $hashedToken,
            'is_active'     =>  true
        ]);

        if(!$tokenEntity || $tokenEntity->isExpired){
            return null;
        }

        $user = $tokenEntity->getUser();
        $newAccessToken = $this->jWTTokenManager->create($user);

        return [
            'access_token'  =>  $newAccessToken,
            'expires_in'    =>  900
        ];
    }

    public function deactivateExistingTokens(WpUser $user){
        $activeTokens   =   $this->refreshTokenRepository->findBy([
            'user'      =>  $user,
            'is_active' =>  true
        ]);

        foreach ($activeTokens as $tok){
            $tok->setIsActive(false);
        }

        $this->entityManager->flush();
    }
}

