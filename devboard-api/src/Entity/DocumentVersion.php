<?php

namespace App\Entity;

use App\Repository\DocumentVersionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DocumentVersionRepository::class)]
class DocumentVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Document $document = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document:read'])]
    private ?string $versionNumber = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $changeDescription = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['document:read'])]
    private ?WpUser $createdBy = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20)]
    #[Groups(['document:read'])]
    private ?string $status = 'draft';

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['document:read'])]
    private ?array $tags = [];

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['document:read'])]
    private ?array $metadata = [];

    #[ORM\Column]
    #[Groups(['document:read'])]
    private bool $isMajor = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['document:read'])]
    private ?int $fileSize = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $fileType = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tags = [];
        $this->metadata = [];
    }

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

    public function getVersionNumber(): ?string
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(string $versionNumber): static
    {
        $this->versionNumber = $versionNumber;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getChangeDescription(): ?string
    {
        return $this->changeDescription;
    }

    public function setChangeDescription(?string $changeDescription): static
    {
        $this->changeDescription = $changeDescription;
        return $this;
    }

    public function getCreatedBy(): ?WpUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?WpUser $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(string $tag): static
    {
        if (!in_array($tag, $this->tags ?? [])) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function removeTag(string $tag): static
    {
        if (($key = array_search($tag, $this->tags ?? [])) !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
        }
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function addMetadata(string $key, $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function removeMetadata(string $key): static
    {
        unset($this->metadata[$key]);
        return $this;
    }

    public function isMajor(): bool
    {
        return $this->isMajor;
    }

    public function setIsMajor(bool $isMajor): static
    {
        $this->isMajor = $isMajor;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(?string $fileType): static
    {
        $this->fileType = $fileType;
        return $this;
    }
} 