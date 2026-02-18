<?php
namespace App\Service;

use App\Entity\Code;
use App\Entity\Gain;
use App\Repository\GainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;

class CodeAllocationService
{
    // On stocke les gains en mémoire pour éviter les requêtes SELECT répétitives
    private ?array $cachedGains = null;

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
            $this->em->persist($code);

            return $gain;
        }

        return null;
    }

    /**
     * @throws RandomException
     */
    private function getRandomGain(): ?Gain
    {
        // Opti : ne chercher les gains en DB que s'ils ne sont pas en cache
        // ou si l'EntityManager a été clear() (ce qui détache les entités).
        if ($this->cachedGains === null || !$this->em->contains($this->cachedGains[0])) {
            $this->cachedGains = $this->gainRepository->findActiveGains();
        }

        if (empty($this->cachedGains)) {
            return null;
        }

        $random = random_int(0, 99);
        $cumulative = 0;

        foreach ($this->cachedGains as $gain) {
            $cumulative += $gain->getProbability();
            if ($random < $cumulative && $gain->canAllocate()) {
                return $gain;
            }
        }

        // Si aucun gain ne convient, retourner le dernier gain actif
        return $this->cachedGains[count($this->cachedGains) - 1] ?? null;
    }
}
