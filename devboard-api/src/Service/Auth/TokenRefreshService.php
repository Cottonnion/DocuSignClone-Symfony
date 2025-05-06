<?php

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\WpUser;
use App\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Manages JWT token refresh operations
 */
class TokenRefreshService
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jWTTokenManager
    ){}

    /**
     * @param WpUser $user User to generate tokens for
     * @return array{success: bool, access_token: string, refresh_token: string, expires_in: int}
     */
    public function generateTokens(WpUser $user): array
    {
        // Delete expired tokens first
        $this->cleanupExpiredTokens();
        
        // Deactivate and remove old tokens
        $this->removeExistingTokens($user);

        $accessToken = $this->jWTTokenManager->create($user);

        $refreshToken = bin2hex(random_bytes(32));

        $refreshTokenEntity = new RefreshToken();
        $refreshTokenEntity->setUser($user);
        $refreshTokenEntity->setRefreshToken(hash('sha256', $refreshToken));
        $refreshTokenEntity->setExpiresAt(new DateTimeImmutable('+14 days'));
        $refreshTokenEntity->setIsActive(true);
        $refreshTokenEntity->setCreatedAt(new DateTimeImmutable());

        $this->entityManager->persist($refreshTokenEntity);
        $this->entityManager->flush();

        return [
            'success'       =>  true,
            'access_token'  =>  $accessToken,
            'refresh_token' =>  $refreshToken,
            'expires_in'    =>  900
        ];
    }

    /**
     * @param string $refreshToken Token to validate
     * @return array{access_token: string, expires_in: int}|null
     */
    public function refreshAccessToken(string $refreshToken):? array
    {
        $hashedToken = hash('sha256', $refreshToken);
        $tokenEntity = $this->refreshTokenRepository->findOneBy([
            'refresh_token' =>  $hashedToken,
            'is_active'     =>  true
        ]);

        if(!$tokenEntity || $tokenEntity->isExpired()){
            // If token is expired or invalid, remove it
            if ($tokenEntity) {
                $this->entityManager->remove($tokenEntity);
                $this->entityManager->flush();
            }
            return null;
        }

        $user = $tokenEntity->getUser();
        $newAccessToken = $this->jWTTokenManager->create($user);

        return [
            'access_token'  =>  $newAccessToken,
            'expires_in'    =>  900
        ];
    }

    /**
     * @param WpUser $user User whose tokens to remove
     */
    private function removeExistingTokens(WpUser $user): void
    {
        $existingTokens = $this->refreshTokenRepository->findBy([
            'user' => $user
        ]);

        foreach ($existingTokens as $token) {
            $this->entityManager->remove($token);
        }

        $this->entityManager->flush();
    }

    /**
     * Removes expired tokens from database
     */
    private function cleanupExpiredTokens(): void
    {
        $now = new DateTimeImmutable();
        $expiredTokens = $this->refreshTokenRepository->findExpiredTokens($now);

        foreach ($expiredTokens as $token) {
            $this->entityManager->remove($token);
        }

        $this->entityManager->flush();
    }

    /**
     * @param WpUser $user User to check for valid refresh token
     * @return RefreshToken|null Valid refresh token if exists
     */
    public function getValidRefreshToken(WpUser $user): ?RefreshToken
    {
        $token = $this->refreshTokenRepository->findOneBy([
            'user' => $user,
            'is_active' => true
        ]);

        if ($token && !$token->isExpired()) {
            return $token;
        }

        return null;
    }

    /**
     * @param string $refreshToken Raw refresh token to validate
     * @return RefreshToken|null Valid refresh token if exists
     */
    public function getValidRefreshTokenByToken(string $refreshToken): ?RefreshToken
    {
        $hashedToken = hash('sha256', $refreshToken);
        $token = $this->refreshTokenRepository->findOneBy([
            'refresh_token' => $hashedToken,
            'is_active' => true
        ]);

        if ($token && !$token->isExpired()) {
            return $token;
        }

        return null;
    }
}

