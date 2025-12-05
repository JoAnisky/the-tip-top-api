<?php
namespace App\Service;

use App\Entity\Code;
use App\Entity\Gain;
use App\Repository\GainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;

class CodeAllocationService
{
    public function __construct(
        private readonly GainRepository         $gainRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @throws RandomException
     */
    public function allocateGainToCode(Code $code): ?Gain
    {
        // Récupérer un gain aléatoire selon les probabilités
        $gain = $this->getRandomGain();

        if ($gain && $gain->canAllocate()) {
            $code->setGain($gain);
            $gain->incrementAllocatedQuantity();
            $this->em->persist($code);
            $this->em->flush();
            return $gain;
        }

        return null;
    }

    /**
     * @throws RandomException
     */
    private function getRandomGain(): ?Gain
    {
        $gains = $this->gainRepository->findActiveGains();

        if (empty($gains)) {
            return null;
        }

        $random = random_int(0, 99);
        $cumulative = 0;

        foreach ($gains as $gain) {
            $cumulative += $gain->getProbability();
            if ($random < $cumulative && $gain->canAllocate()) {
                return $gain;
            }
        }

        // Si aucun gain ne convient, retourner le dernier gain actif
        return $gains[count($gains) - 1] ?? null;
    }
}
