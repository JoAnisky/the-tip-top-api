<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\TicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ApiResource]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $issued_on = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $receiptAmount = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Store $store = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    private ?Code $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIssuedOn(): ?\DateTime
    {
        return $this->issued_on;
    }

    public function setIssuedOn(\DateTime $issued_on): static
    {
        $this->issued_on = $issued_on;

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
