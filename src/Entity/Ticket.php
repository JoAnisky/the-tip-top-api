<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_EMPLOYEE')"),
    ],
    normalizationContext: ['groups' => ['ticket:read']],
    denormalizationContext: ['groups' => ['ticket:create']],
)]
class Ticket
{
    #[Groups(['ticket:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Groups(['ticket:read', 'ticket:create'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $issuedOn = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['ticket:read', 'ticket:create'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $receiptAmount = null;

    #[Groups(['ticket:read'])]
    #[ORM\ManyToOne(inversedBy: 'tickets')]
    private ?User $user = null;

    #[Assert\NotNull]
    #[Groups(['ticket:read', 'ticket:create'])]
    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Store $store = null;

    #[Groups(['ticket:read'])]
    #[ORM\ManyToOne(inversedBy: 'tickets')]
    private ?Code $code = null;

    public function __construct()
    {
        $this->issuedOn = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIssuedOn(): ?\DateTimeImmutable
    {
        return $this->issuedOn;
    }

    public function setIssuedOn(\DateTimeImmutable $issuedOn): static
    {
        $this->issuedOn = $issuedOn;

        return $this;
    }

    public function getReceiptAmount(): ?string
    {
        return $this->receiptAmount;
    }

    public function setReceiptAmount(string $receiptAmount): static
    {
        $this->receiptAmount = $receiptAmount;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setStore(?Store $store): static
    {
        $this->store = $store;

        return $this;
    }

    public function getCode(): ?Code
    {
        return $this->code;
    }

    public function setCode(?Code $code): static
    {
        $this->code = $code;

        return $this;
    }
}
