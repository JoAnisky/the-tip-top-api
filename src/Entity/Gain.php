<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\GainRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GainRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            security: "is_granted('ROLE_USER')"
        ),
        new Post(
            denormalizationContext: ['groups' => ['gain:create']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')"
        ),
    ],
    normalizationContext: ['groups' => ['gain:read']],
    denormalizationContext: ['groups' => ['gain:update']],
)]
class Gain
{
    #[Groups(['gain:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['gain:read', 'gain:create', 'gain:update', 'code:read'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['gain:read', 'gain:create', 'gain:update','code:read' ])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[Assert\Positive]
    #[Groups(['gain:read', 'gain:create', 'gain:update', 'code:read'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $value = null;

    /**
     * Probabilité en pourcentage (0-100)
     * Stocké comme entier : 60 pour 60%, 20 pour 20%, etc.
     * 20% des tickets offrent une boite de 100g d’un thé détox ou d’infusion
     * 10% des tickets offrent une boite de 100g d’un thé signature
     * 6% des tickets offrent un coffret découverte d’une valeur de 39€
     * 4% des tickets offrent un coffret découverte d’une valeur de 69€
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['gain:read', 'gain:create'])]
    #[ORM\Column]
    private ?int $probability = null;

    /**
     * Date limite pour réclamer le gain
     * Les joueurs auront les 30 jours du jeu concours ainsi que 30 jours supplémentaires à compter de la date de clôture du jeu pour aller
     * sur le site internet afin tester le code de leur(s) ticket(s) et réclamer leur lot en magasin ou en ligne.
     */
    #[Groups(['gain:read', 'gain:create'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimDeadLine = null;

    /**
     * Nombre d'exemplaires de ce gain à distribuer
     * Ex: 300 000 infuseurs (60% de 500 000)
     */
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[Groups(['gain:read', 'gain:create'])]
    #[ORM\Column]
    private ?int $maxQuantity = 0;

    /**
     * Nombre de gains déjà attribués
     */
    #[Groups(['gain:read'])]
    #[ORM\Column]
    private int $allocatedQuantity = 0;

    /**
     * @var Collection<int, Code>
     */
    #[ORM\OneToMany(targetEntity: Code::class, mappedBy: 'gain')]
    private Collection $codes;

    public function __construct()
    {
        $this->codes = new ArrayCollection();
    }

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


    public function getClaimDeadLine(): ?\DateTimeImmutable
    {
        return $this->claimDeadLine;
    }

    public function setClaimDeadLine(?\DateTimeImmutable $claimDeadLine): static
    {
        $this->claimDeadLine = $claimDeadLine;

        return $this;
    }

    public function getMaxQuantity(): ?int
    {
        return $this->maxQuantity;
    }

    public function setMaxQuantity(int $maxQuantity): static
    {
        $this->maxQuantity = $maxQuantity;
        return $this;
    }

    public function getAllocatedQuantity(): int
    {
        return $this->allocatedQuantity;
    }

    public function setAllocatedQuantity(int $allocatedQuantity): static
    {
        $this->allocatedQuantity = $allocatedQuantity;
        return $this;
    }

    public function incrementAllocatedQuantity(): static
    {
        $this->allocatedQuantity++;
        return $this;
    }

    public function getCodes(): Collection
    {
        return $this->codes;
    }

    public function addCode(Code $code): static
    {
        if (!$this->codes->contains($code)) {
            $this->codes->add($code);
            $code->setGain($this);
        }
        return $this;
    }

    public function canAllocate(): bool
    {
        // On peut allouer tant qu'il reste des codes disponibles
        // Il faut compter combien de codes ont ce gain
        return $this->codes->count() < $this->maxQuantity;
    }
}
