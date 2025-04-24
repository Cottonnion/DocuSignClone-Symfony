<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\WpCreateUpdateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WpCreateUpdateRepository::class)]
// This line makes the class an API resource (it will be used as a resource in the API).
#[ApiResource]
class WpCreateUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
