<?php

namespace App\Entity;

use App\Repository\GrandLotDrawRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GrandLotDrawRepository::class)]
class GrandLotDraw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $attribution_date = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttributionDate(): ?\DateTimeImmutable
    {
        return $this->attribution_date;
    }

    public function setAttributionDate(\DateTimeImmutable $attribution_date): static
    {
        $this->attribution_date = $attribution_date;

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
}
