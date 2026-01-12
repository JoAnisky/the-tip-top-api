<?php

namespace App\Command;

use App\Repository\CodeRepository;
use App\Service\CodeAllocationService;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'app:allocate:gains',
    description: 'Attribue un gain à chaque code généré selon les probabilités.',
)]
class AllocateGainsCommand extends Command
{
    private const BATCH_SIZE = 100; // Plus petit que DBAL car l'ORM consomme plus

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CodeAllocationService $codeAllocationService
    ) {
        parent::__construct();
    }

    /**
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1024M');
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('allocation');

        // récupérer uniquement les codes qui n'ont pas encore de gain
        $io->note('Récupération des codes sans gain...');

        // Utilisation de l'itérateur pour ne pas charger 500k objets d'un coup en mémoire
        $query = $this->entityManager->createQuery('SELECT c FROM App\Entity\Code c WHERE c.gain IS NULL');
        $iterableResult = $query->toIterable();

        // Compter pour la barre de progression (optionnel)
        $countQuery = $this->entityManager->createQuery('SELECT COUNT(c.id) FROM App\Entity\Code c WHERE c.gain IS NULL');
        $total = $countQuery->getSingleScalarResult();

        if ($total == 0) {
            $io->success('Tous les codes ont déjà un gain.');
            return Command::SUCCESS;
        }

        $io->progressStart($total);
        $i = 0;

        foreach ($iterableResult as $code) {
            // utilise le service pour attribuer le gain
            $this->codeAllocationService->allocateGainToCode($code);

            // batch processing
            if (($i % self::BATCH_SIZE) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(); // Libère la mémoire
            }

            $i++;
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->progressFinish();
        $event = $stopwatch->stop('allocation');

        $io->success(sprintf(
            'Attribution terminée ! %d codes mis à jour en %.2f secondes.',
            $i,
            $event->getDuration() / 1000
        ));

        return Command::SUCCESS;
    }
}
