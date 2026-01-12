<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\ClaimInput;
use App\Dto\CodeInput;
use App\Repository\CodeRepository;
use App\State\CodeValidationProcessor;
use App\State\CodeClaimProcessor;
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
            normalizationContext: ['groups' => ['code:read']],
            denormalizationContext: ['groups' => ['code:validate']],
            security: "is_granted('ROLE_USER')",
            input: CodeInput::class,
            name: 'validate_code',
            processor: CodeValidationProcessor::class,
        ),
        // Seul l'employé en boutique (ou l'admin) peut marquer un code comme utilisé via PATCH
        new Post(
            uriTemplate: '/codes/claim',
            normalizationContext: ['groups' => ['code:read']],
            denormalizationContext: ['groups' => ['code:claim']],
            security: "is_granted('ROLE_EMPLOYEE')",
            input: ClaimInput::class,
            output: Code::class,
            name: 'claim_code',
            processor: CodeClaimProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['code:read']],
    denormalizationContext: ['groups' => ['code:update']],
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact'])]
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

    /**
     * Le code a-t-il été validé par l'utilisateur ?
     */
    #[Groups(['code:read'])]
    #[ORM\Column(options: ["default" => 0])]
    private bool $isValidated;

    /**
     * Date à laquelle le code a été validé (renseigné par l'utilisateur)
     * C'est le moment où le client entre son code sur le site
     */
    #[Groups(['code:read'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedOn = null;

    /**
     * Le lot a-t-il été remis physiquement au client ?
     */
    #[Groups(['code:read', 'code:update'])]
    #[ORM\Column(options: ["default" => 0])]
    private bool $isClaimed;

    /**
     * Date à laquelle le lot a été remis au client
     * Rempli par l'employé en magasin
     */
    #[Groups(['code:read'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedOn = null;

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
    #[ORM\ManyToOne(targetEntity: Gain::class, inversedBy: 'codes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Gain $gain = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['code:read'])]
    private ?User $winner = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->isValidated = false;
        $this->isClaimed = false;
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

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    /**
     * Valider le code (quand l'utilisateur le renseigne)
     */
    public function setIsValidated(bool $isValidated): static
    {
        $this->isValidated = $isValidated;
        if ($isValidated && !$this->validatedOn) {
            $this->validatedOn = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getValidatedOn(): ?\DateTimeImmutable
    {
        return $this->validatedOn;
    }

    public function setValidatedOn(?\DateTimeImmutable $validatedOn): static
    {
        $this->validatedOn = $validatedOn;
        return $this;
    }

    public function isClaimed(): bool
    {
        return $this->isClaimed;
    }

    /**
     * Marquer le lot comme remis (par l'employé en magasin)
     * Seul ROLE_EMPLOYEE peut faire cette action via PATCH
     */
    public function setIsClaimed(bool $isClaimed): static
    {
        $this->isClaimed = $isClaimed;
        if ($isClaimed && !$this->claimedOn) {
            $this->claimedOn = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getClaimedOn(): ?\DateTimeImmutable
    {
        return $this->claimedOn;
    }

    public function setClaimedOn(?\DateTimeImmutable $claimedOn): static
    {
        $this->claimedOn = $claimedOn;
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

    public function setGain(?Gain $gain): static
    {
        $this->gain = $gain;

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
