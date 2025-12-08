<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\GrandLotDrawRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GrandLotDrawRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['grandlotdraw:read']],
    denormalizationContext: ['groups' => ['grandlotdraw:create', 'grandlotdraw:update']],
)]
class GrandLotDraw
{
    #[Groups(['grandlotdraw:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Groups(['grandlotdraw:read', 'grandlotdraw:create', 'grandlotdraw:update'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $attributionDate = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['grandlotdraw:read', 'grandlotdraw:create', 'grandlotdraw:update'])]
    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[Assert\NotNull]
    #[Groups(['grandlotdraw:read'])]
    #[ORM\OneToOne(inversedBy: 'grandLotDraw')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $winner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttributionDate(): ?\DateTimeImmutable
    {
        return $this->attributionDate;
    }

    public function setAttributionDate(\DateTimeImmutable $attributionDate): static
    {
        $this->attributionDate = $attributionDate;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getWinner(): ?User
    {
        return $this->winner;
    }

    public function setWinner(?User $winner): static
    {
        $this->winner = $winner;

        return $this;
    }
}
