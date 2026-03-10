<?php

namespace App\Tests\Fixtures;

use App\Entity\Code;
use App\Entity\Gain;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures isolées pour la suite de tests fonctionnels.
 * Appartiennent au groupe "test" pour ne jamais être chargées en dev/prod.
 *
 * Données stables utilisées dans les tests :
 *   - USER_EMAIL / USER_PASSWORD  → utilisateur ROLE_USER
 *   - VALID_CODE                  → code valide, non expiré, non validé
 *   - ALREADY_VALIDATED_CODE      → code déjà validé
 *   - EXPIRED_CODE                → code expiré
 */
class TestFixtures extends Fixture implements FixtureGroupInterface
{
    // Constantes référençables depuis les tests pour éviter toute duplication
    public const USER_EMAIL    = 'testuser@thetiptop.fr';
    public const USER_PASSWORD = 'Password1!';

    public const VALID_CODE             = 'VALID00001';
    public const ALREADY_VALIDATED_CODE = 'USED000001';
    public const EXPIRED_CODE           = 'EXPIR00001';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {}

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function load(ObjectManager $manager): void
    {
        // --- Gain ---
        $gain = new Gain();
        $gain->setName('Infuseur à thé');
        $gain->setProbability(60);
        $gain->setMaxQuantity(300000);
        $manager->persist($gain);

        // --- Utilisateur de test ---
        $user = new User();
        $user->setEmail(self::USER_EMAIL);
        $user->setPassword($this->hasher->hashPassword($user, self::USER_PASSWORD));
        $user->setRoles(['ROLE_USER']);
        $manager->persist($user);

        // --- Code valide ---
        $validCode = new Code();
        $validCode->setCode(self::VALID_CODE);
        $validCode->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $validCode->setGain($gain);
        $manager->persist($validCode);

        // --- Code déjà validé ---
        $usedCode = new Code();
        $usedCode->setCode(self::ALREADY_VALIDATED_CODE);
        $usedCode->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $usedCode->setGain($gain);
        $usedCode->setIsValidated(true);
        $manager->persist($usedCode);

        // --- Code expiré ---
        $expiredCode = new Code();
        $expiredCode->setCode(self::EXPIRED_CODE);
        $expiredCode->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $expiredCode->setGain($gain);
        $manager->persist($expiredCode);

        $manager->flush();
    }
}
