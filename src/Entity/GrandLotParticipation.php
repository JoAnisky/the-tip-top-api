<?php

namespace App\Entity;

use App\Repository\GrandLotParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GrandLotParticipationRepository::class)]
class GrandLotParticipation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $participationDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipationDate(): ?\DateTimeImmutable
    {
        return $this->participationDate;
    }

    public function setParticipationDate(\DateTimeImmutable $participationDate): static
    {
        $this->participationDate = $participationDate;

        return $this;
    }
}
