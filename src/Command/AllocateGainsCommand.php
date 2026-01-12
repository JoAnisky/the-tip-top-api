<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'app:allocate:gains',
    description: 'Attribue les gains massivement via DBAL (très rapide)',
)]
class AllocateGainsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1024M');
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('allocation');

        // récupérer les gains actifs et leurs probabilités
        $gains = $this->connection->fetchAllAssociative(
            'SELECT id, probability, name FROM gain'
        );

        if (empty($gains)) {
            $io->error('Aucun gain actif trouvé.');
            return Command::FAILURE;
        }

        // récupérer tous les IDs des codes sans gain
        $io->info('Chargement des IDs des codes...');
        $codeIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM code WHERE gain_id IS NULL'
        );
        $totalCodes = count($codeIds);

        if ($totalCodes === 0) {
            $io->success('Tous les codes ont déjà un gain.');
            return Command::SUCCESS;
        }

        // mélanger les IDs pour l'aléatoire
        shuffle($codeIds);

        $io->note(sprintf('Attribution de %d codes...', $totalCodes));
        $offset = 0;

        foreach ($gains as $index => $gain) {
            // Calcul du nombre de codes pour ce gain selon sa probabilité
            // Pour le dernier gain, on prend tout ce qui reste pour éviter les arrondis
            if ($index === count($gains) - 1) {
                $countForThisGain = $totalCodes - $offset;
            } else {
                $countForThisGain = (int) round(($gain['probability'] / 100) * $totalCodes);
            }

            if ($countForThisGain > 0) {
                $slice = array_slice($codeIds, $offset, $countForThisGain);

                $io->writeln(sprintf(' - Lot "%s" (%d%%) : %d codes', $gain['name'], $gain['probability'], $countForThisGain));

                // UPDATE par lots de 5000 IDs pour ne pas faire exploser la requête SQL (limite de taille)
                $chunks = array_chunk($slice, 5000);
                foreach ($chunks as $chunk) {
                    $this->connection->executeStatement(
                        "UPDATE code SET gain_id = ? WHERE id IN (?)",
                        [$gain['id'], $chunk],
                        [\PDO::PARAM_INT, Connection::PARAM_INT_ARRAY]
                    );
                }

                // Mettre à jour la quantité allouée dans la table gain
                $this->connection->executeStatement(
                    'UPDATE gain SET allocated_quantity = allocated_quantity + ? WHERE id = ?',
                    [$countForThisGain, $gain['id']]
                );

                $offset += $countForThisGain;
            }
        }

        $event = $stopwatch->stop('allocation');
        $io->success(sprintf('Terminé en %.2f secondes !', $event->getDuration() / 1000));

        return Command::SUCCESS;
    }
}
