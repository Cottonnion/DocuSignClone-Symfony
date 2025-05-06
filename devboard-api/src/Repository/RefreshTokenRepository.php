<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }
    //    }

    public function findExpiredTokens(DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.expires_at < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}