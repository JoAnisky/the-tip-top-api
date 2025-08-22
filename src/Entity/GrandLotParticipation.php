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

    #[ORM\OneToOne(mappedBy: 'GrandLotParticipation', cascade: ['persist', 'remove'])]
    private ?User $user = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // unset the owning side of the relation if necessary
        if ($user === null && $this->user !== null) {
            $this->user->setGrandLotParticipation(null);
        }

        // set the owning side of the relation if necessary
        if ($user !== null && $user->getGrandLotParticipation() !== $this) {
            $user->setGrandLotParticipation($this);
        }

        $this->user = $user;

        return $this;
    }
}
