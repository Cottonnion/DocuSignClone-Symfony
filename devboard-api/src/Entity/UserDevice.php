<?php

namespace App\Entity;

use App\Repository\UserDeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserDeviceRepository::class)]
class UserDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_device:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'devices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?WpUser $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['user_device:read'])]
    private ?string $deviceId = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['user_device:read'])]
    private ?string $deviceName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['user_device:read'])]
    private ?string $deviceType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user_device:read'])]
    private ?string $pushToken = null;

    #[ORM\Column]
    #[Groups(['user_device:read'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['user_device:read'])]
    private ?\DateTimeImmutable $lastActiveAt = null;

    #[ORM\Column]
    #[Groups(['user_device:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastActiveAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?WpUser
    {
        return $this->user;
    }

    public function setUser(?WpUser $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): static
    {
        $this->deviceId = $deviceId;
        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(string $deviceName): static
    {
        $this->deviceName = $deviceName;
        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(string $deviceType): static
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getPushToken(): ?string
    {
        return $this->pushToken;
    }

    public function setPushToken(?string $pushToken): static
    {
        $this->pushToken = $pushToken;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastActiveAt(): ?\DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function setLastActiveAt(\DateTimeImmutable $lastActiveAt): static
    {
        $this->lastActiveAt = $lastActiveAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
} 