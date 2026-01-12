<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Code;
use App\Repository\CodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

readonly class CodeValidationProcessor implements ProcessorInterface
{
    public function __construct(
        private CodeRepository         $codeRepository,
        private EntityManagerInterface $em,
        private Security               $security,
        private RateLimiterFactory     $codeValidationLimiter,
    )
    {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Code
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        // --- RATE LIMITER (anti brute-force) ---
        // On crée une clé unique basée sur l'identifiant de l'utilisateur
        $limiter = $this->codeValidationLimiter->create($user->getUserIdentifier());

        // On consomme 1 jeton. Si la limite est dépassée, on lance une exception
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException('Trop de tentatives. Réessayez dans une minute.');
        }

        $codeValue = $data->code;

        $code = $this->codeRepository->findOneBy(['code' => $codeValue]);

        if (!$code || $code->isValidated() || $code->getExpiresAt() < new \DateTimeImmutable()) {
            // On renvoie exactement la même erreur HTTP (400) et le même message : éviter l'énumération de données.
            throw new BadRequestHttpException('Le code saisi est invalide ou a déjà été utilisé.');
        }

        // Attribution
        $code->setIsValidated(true);
        $code->setWinner($user);

        $this->em->flush();

        return $code;
    }
}
