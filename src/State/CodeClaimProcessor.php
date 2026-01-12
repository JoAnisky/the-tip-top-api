<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\CodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CodeClaimProcessor implements ProcessorInterface
{
    public function __construct(
        private CodeRepository $codeRepository,
        private EntityManagerInterface $em
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // trouver le code par sa valeur string (venant du DTO)
        $code = $this->codeRepository->findOneBy(['code' => $data->code]);

        if (!$code) {
            throw new NotFoundHttpException('Code introuvable.');
        }

        // vérifier si le client l'a bien validé sur le site d'abord
        if (!$code->isValidated()) {
            throw new BadRequestHttpException("Ce code n'a pas encore été activé par le client sur le site.");
        }

        // vérifier s'il n'est pas déjà récupéré
        if ($code->isClaimed()) {

            $gainLabel = $code->getGain() ? $code->getGain()->getName() : 'Lot inconnu';

            // le message contient le nom du lot pour que l'employé puisse informer le client
            $message = sprintf(
                'Le lot "%s" a déjà été remis à ce client le %s.',
                $gainLabel,
                $code->getClaimedOn()->format('d/m/Y à H:i')
            );

            throw new BadRequestHttpException($message);
        }

        // marquer comme remis (le setter s'occupe de la date de récupération)
        $code->setIsClaimed(true);

        $this->em->flush();

        return $code;
    }
}
