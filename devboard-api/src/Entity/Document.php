<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['document:read'])]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['draft', 'sent', 'signed', 'cancelled'])]
    #[Groups(['document:read'])]
    private ?string $status = 'draft';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['document:read'])]
    private ?WpUser $createdBy = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['document:read'])]
    private ?\DateTimeInterface $signDeadline = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private bool $isTemplate = false;

    #[ORM\OneToOne(mappedBy: 'document', cascade: ['persist', 'remove'])]
    private ?Template $template = null;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: Signatory::class, orphanRemoval: true)]
    #[Groups(['document:read'])]
    private Collection $signatories;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->signatories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getSignDeadline(): ?\DateTimeInterface
    {
        return $this->signDeadline;
    }

    public function setSignDeadline(?\DateTimeInterface $signDeadline): static
    {
        $this->signDeadline = $signDeadline;
        return $this;
    }

    public function isTemplate(): bool
    {
        return $this->isTemplate;
    }

    public function setIsTemplate(bool $isTemplate): static
    {
        $this->isTemplate = $isTemplate;
        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return Collection<int, Signatory>
     */
    public function getSignatories(): Collection
    {
        return $this->signatories;
    }

    public function addSignatory(Signatory $signatory): static
    {
        if (!$this->signatories->contains($signatory)) {
            $this->signatories->add($signatory);
            $signatory->setDocument($this);
        }
        return $this;
    }

    public function removeSignatory(Signatory $signatory): static
    {
        if ($this->signatories->removeElement($signatory)) {
            if ($signatory->getDocument() === $this) {
                $signatory->setDocument(null);
            }
        }
        return $this;
    }

    public function getSignedCount(): int
    {
        return $this->signatories->filter(fn(Signatory $signatory) => $signatory->isSigned())->count();
    }

    public function isFullySigned(): bool
    {
        return $this->getSignedCount() === $this->signatories->count();
    }
}
