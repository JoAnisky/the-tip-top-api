<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Enum\Gender;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ApiResource]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $birthdate = null;

    #[ORM\Column(length: 10, nullable: true, enumType: Gender::class)]
//    #[Assert\Choice(callback: [Gender::class, 'cases'])]
    private ?Gender $gender = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneNumber = null;

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
    #[ORM\OneToMany(targetEntity: SocialAccount::class, mappedBy: 'user')]
    private Collection $socialAccounts;

    #[ORM\OneToOne(inversedBy: 'user', cascade: ['persist'])]
    private ?GrandLotParticipation $grandLotParticipation = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->socialAccounts = new ArrayCollection();
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

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getBirthdate(): ?\DateTimeImmutable
    {
        return $this->birthdate;
    }

    public function setBirthdate(?\DateTimeImmutable $birthdate): static
    {
        $this->birthdate = $birthdate;

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
}
