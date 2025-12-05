<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\CodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CodeRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            security : "is_granted('ROLE_ADMIN')"
        ),
        // Endpoint pour soumettre/valider un code
        new Post(
            uriTemplate: '/codes/validate',
            security: "is_granted('ROLE_USER')",
            name: 'validate_code'
        ),
        // Seul l'employé en boutique peut marquer un code comme utilisé via PATCH
        new Patch(
            security: "is_granted('ROLE_EMPLOYEE')",
        ),
    ],
    normalizationContext: ['groups' => ['code:read']],
    denormalizationContext: ['groups' => ['code:update']],
)]
class Code
{
    #[Groups(['code:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 10)]
    #[Groups(['code:read'])]
    #[ORM\Column(length: 10, unique: true)]
    private ?string $code = null;

    #[Groups(['code:read'])]
    #[ORM\Column]
    private ?bool $isUsed = null;

    #[Groups(['code:read'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedOn = null;

    #[Assert\NotBlank]
    #[Groups(['code:read'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'code')]
    private Collection $tickets;

    #[Assert\NotNull]
    #[Groups(['code:read'])]
    #[ORM\OneToOne(inversedBy: 'codes', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Gain $gain = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->isUsed = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function isUsed(): ?bool
    {
        return $this->isUsed;
    }

    /**
     * Marquer le code comme utilisé
     * Seul ROLE_EMPLOYE peut faire cette action via PATCH
     */
    public function setIsUsed(bool $isUsed): static
    {
        $this->isUsed = $isUsed;
        if ($isUsed && !$this->usedOn) {
            $this->usedOn = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getUsedOn(): ?\DateTimeImmutable
    {
        return $this->usedOn;
    }

    public function setUsedOn(?\DateTimeImmutable $usedOn): static
    {
        $this->usedOn = $usedOn;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setCode($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getCode() === $this) {
                $ticket->setCode(null);
            }
        }

        return $this;
    }

    public function getGain(): ?Gain
    {
        return $this->gain;
    }

    public function setGain(Gain $gain): static
    {
        $this->gain = $gain;

        return $this;
    }
}
