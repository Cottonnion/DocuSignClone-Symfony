<?php

namespace App\Repository;

use App\Entity\UserDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDevice>
 */
class UserDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDevice::class);
    }

    public function findByUserAndDeviceId(int $userId, string $deviceId): ?UserDevice
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.user = :userId')
            ->andWhere('ud.deviceId = :deviceId')
            ->setParameter('userId', $userId)
            ->setParameter('deviceId', $deviceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveDevicesByUser(int $userId): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.user = :userId')
            ->andWhere('ud.isActive = true')
            ->setParameter('userId', $userId)
            ->orderBy('ud.lastActiveAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDevicesByPushToken(string $pushToken): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.pushToken = :pushToken')
            ->andWhere('ud.isActive = true')
            ->setParameter('pushToken', $pushToken)
            ->getQuery()
            ->getResult();
    }
} 