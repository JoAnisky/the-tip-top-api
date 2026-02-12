<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Interface\OwnerAwareInterface;
use App\Repository\SocialAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SocialAccountRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_provider_account', columns: ['provider_name', 'provider_id'])]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER') and object.getUser() == user"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Delete(security: "is_granted('ROLE_USER') and object.getUser() == user"),
    ],
    normalizationContext: ['groups' => ['socialaccount:read']],
    denormalizationContext: ['groups' => ['socialaccount:create']],
)]
class SocialAccount implements OwnerAwareInterface
{
    #[Groups(['socialaccount:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['google', 'facebook'])]
    #[Groups(['socialaccount:read', 'socialaccount:create'])]
    #[ORM\Column(length: 50)]
    private ?string $providerName = null;

    #[Assert\NotBlank]
    #[Groups(['socialaccount:read', 'socialaccount:create'])]
    #[ORM\Column(length: 255)]
    private ?string $providerId = null;

    #[Groups(['socialaccount:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerEmail = null; // Email du provider (peut différer de User::email)

    #[Groups(['socialaccount:read'])]
    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[Groups(['socialaccount:read'])]
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[Groups(['socialaccount:read'])]
    #[ORM\ManyToOne(inversedBy: 'socialAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): static
    {
        $this->providerName = $providerName;
        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(string $providerId): static
    {
        $this->providerId = $providerId;
        return $this;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function setProviderEmail(?string $providerEmail): static
    {
        $this->providerEmail = $providerEmail;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function updateLastUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();
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

    // Implémentation OwnerAwareInterface
    public function getOwner(): ?User
    {
        return $this->user;
    }
}
