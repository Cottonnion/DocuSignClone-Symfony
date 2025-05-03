<?php

namespace App\Entity;

use App\Repository\SignatoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SignatoryRepository::class)]
class Signatory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['signatory:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'signatories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Document $document = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['signatory:read', 'signatory:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['signatory:read', 'signatory:write'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['signatory:read', 'signatory:write'])]
    private ?int $signingOrder = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['signatory:read'])]
    private ?string $token = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['pending', 'signed', 'declined'])]
    #[Groups(['signatory:read'])]
    private ?string $status = 'pending';

    #[ORM\Column(nullable: true)]
    #[Groups(['signatory:read'])]
    private ?\DateTimeImmutable $signedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Groups(['signatory:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['signatory:read'])]
    private ?string $userAgent = null;

    #[ORM\Column]
    #[Groups(['signatory:read'])]
    private ?bool $signed = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSigningOrder(): ?int
    {
        return $this->signingOrder;
    }

    public function setSigningOrder(int $signingOrder): static
    {
        $this->signingOrder = $signingOrder;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSignedAt(): ?\DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeImmutable $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function isSigned(): bool
    {
        return $this->signed;
    }

    public function setSigned(bool $signed): static
    {
        $this->signed = $signed;
        if ($signed) {
            $this->signedAt = new \DateTimeImmutable();
        }
        return $this;
    }
}
