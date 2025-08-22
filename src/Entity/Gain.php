<?php

namespace App\Entity;

use App\Repository\GainRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GainRepository::class)]
class Gain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $value = null;

    #[ORM\Column]
    private ?int $probability = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $allocationDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimDate = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getProbability(): ?int
    {
        return $this->probability;
    }

    public function setProbability(int $probability): static
    {
        $this->probability = $probability;

        return $this;
    }

    public function getAllocationDate(): ?\DateTimeImmutable
    {
        return $this->allocationDate;
    }

    public function setAllocationDate(?\DateTimeImmutable $allocationDate): static
    {
        $this->allocationDate = $allocationDate;

        return $this;
    }

    public function getClaimDate(): ?\DateTimeImmutable
    {
        return $this->claimDate;
    }

    public function setClaimDate(?\DateTimeImmutable $claimDate): static
    {
        $this->claimDate = $claimDate;

        return $this;
    }
}
