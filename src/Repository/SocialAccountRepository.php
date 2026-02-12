<?php

namespace App\Repository;

use App\Entity\SocialAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SocialAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialAccount::class);
    }

    /**
     * Trouve un compte social par provider et ID
     */
    public function findByProviderAndId(string $provider, string $providerId): ?SocialAccount
    {
        return $this->findOneBy([
            'providerName' => $provider,
            'providerId' => $providerId
        ]);
    }

    /**
     * Trouve tous les comptes sociaux d'un user
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Vérifie si un provider ID est déjà utilisé
     */
    public function existsByProvider(string $provider, string $providerId): bool
    {
        return $this->count([
                'providerName' => $provider,
                'providerId' => $providerId
            ]) > 0;
    }
}
