<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class TokenService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $refreshTokenTtl
    )
    {}

    public function createAccessToken(User $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * @throws \Exception
     */
    public function createRefreshToken(User $user): RefreshToken
    {
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setExpiresAt(
            new \DateTimeImmutable(sprintf('+%d seconds', $this->refreshTokenTtl))
        );

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $refreshToken;
    }

    public function validateRefreshToken(string $token): ?RefreshToken
    {
        $refreshToken = $this->entityManager->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if (!$refreshToken || !$refreshToken->isValid()) {
            return null;
        }

        return $refreshToken;
    }

    public function revokeRefreshToken(RefreshToken $token): void
    {
        $token->setRevoked(true);
        $this->entityManager->flush();
    }

    public function rotateRefreshToken(RefreshToken $oldToken): RefreshToken
    {
        $this->revokeRefreshToken($oldToken);
        return $this->createRefreshToken($oldToken->getUser());
    }
}
