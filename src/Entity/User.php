<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\Gender;
use App\Repository\UserRepository;
use App\State\UserPasswordHasher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_EMPLOYEE')"
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserPasswordHasher::class
        ),
        new Get(
            security: "is_granted('ROLE_EMPLOYEE') or object.getId() == user.getId()"
        ),
        new Put(
            security: "is_granted('ROLE_ADMIN') or (object.getId() == user.getId() and is_granted('ROLE_USER'))",
            processor: UserPasswordHasher::class
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or (object.getId() == user.getId() and is_granted('ROLE_USER'))",
            processor: UserPasswordHasher::class
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:create', 'user:update']],
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity('email')]
#[ApiFilter(SearchFilter::class, properties: [
    'email' => 'ipartial',
    'phoneNumber' => 'exact',
    'firstName' => 'ipartial',
    'lastName' => 'ipartial'
])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[Groups(['user:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins 8 caractères', groups: ['user:create', 'user:update'])]
    #[Groups(['user:create', 'user:update'])]
    private ?string $plainPassword = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 10, nullable: true, enumType: Gender::class)]
    #[Assert\Choice(callback: [Gender::class, 'cases'])]
    private ?Gender $gender = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $city = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $country = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[Groups(['user:read'])]
    #[ORM\Column]
    private ?\DateTimeImmutable $registeredIn = null;

    #[ORM\OneToOne(mappedBy: 'winner')]
    private ?GrandLotDraw $grandLotDraw = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'user')]
    private Collection $tickets;

    /**
     * @var Collection<int, SocialAccount>
     */
    #[ORM\OneToMany(targetEntity: SocialAccount::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $socialAccounts;

    #[ORM\OneToOne(inversedBy: 'user', cascade: ['persist'])]
    private ?GrandLotParticipation $grandLotParticipation = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var Collection<int, RefreshToken>
     */
    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'user')]
    private Collection $refreshTokens;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $address = null;

    #[Groups(['user:read', 'user:create', 'user:update'])]
    #[ORM\Column(type: 'boolean')]
    private ?bool $newsletter = false;

    #[Groups(['user:read'])]
    #[ORM\Column(type: 'boolean')]
    private ?bool $isVerified = false;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->socialAccounts = new ArrayCollection();
        $this->registeredIn = new \DateTimeImmutable();
        $this->refreshTokens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * A la place de getUserName(), on utilise getUserIdentifier() dans Symfony 7
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // garantit que chaque utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // (e.g., a plain password which is not needed after hashing it)
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthdate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(?Gender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getRegisteredIn(): ?\DateTimeImmutable
    {
        return $this->registeredIn;
    }

    public function setRegisteredIn(\DateTimeImmutable $registeredIn): static
    {
        $this->registeredIn = $registeredIn;

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
            $ticket->setUser($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getUser() === $this) {
                $ticket->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    public function getSocialAccounts(): Collection
    {
        return $this->socialAccounts;
    }

    public function addSocialAccount(SocialAccount $socialAccount): static
    {
        if (!$this->socialAccounts->contains($socialAccount)) {
            $this->socialAccounts->add($socialAccount);
            $socialAccount->setUser($this);
        }

        return $this;
    }

    public function removeSocialAccount(SocialAccount $socialAccount): static
    {
        if ($this->socialAccounts->removeElement($socialAccount)) {
            // set the owning side to null (unless already changed)
            if ($socialAccount->getUser() === $this) {
                $socialAccount->setUser(null);
            }
        }

        return $this;
    }

    public function getGrandLotParticipation(): ?GrandLotParticipation
    {
        return $this->grandLotParticipation;
    }

    public function setGrandLotParticipation(?GrandLotParticipation $grandLotParticipation): static
    {
        $this->grandLotParticipation = $grandLotParticipation;

        return $this;
    }

    /**
     * @return Collection<int, RefreshToken>
     */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    public function addRefreshToken(RefreshToken $refreshToken): static
    {
        if (!$this->refreshTokens->contains($refreshToken)) {
            $this->refreshTokens->add($refreshToken);
            $refreshToken->setUser($this);
        }

        return $this;
    }

    public function removeRefreshToken(RefreshToken $refreshToken): static
    {
        if ($this->refreshTokens->removeElement($refreshToken)) {
            // set the owning side to null (unless already changed)
            if ($refreshToken->getUser() === $this) {
                $refreshToken->setUser(null);
            }
        }

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getNewsletter(): ?bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(bool $newsletter): static
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    /**
     * Vérifie si un compte social est déjà lié
     */
    public function hasSocialAccount(string $provider): bool
    {
        foreach ($this->socialAccounts as $account) {
            if ($account->getProviderName() === $provider) {
                return true;
            }
        }
        return false;
    }

    /**
     * Récupère un compte social spécifique
     */
    public function getSocialAccount(string $provider): ?SocialAccount
    {
        foreach ($this->socialAccounts as $account) {
            if ($account->getProviderName() === $provider) {
                return $account;
            }
        }
        return null;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }
}
