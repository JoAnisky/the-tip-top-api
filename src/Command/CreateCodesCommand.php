<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'app:create:codes',
    description: 'Génère les 500 000 codes en base de données',
)]
class CreateCodesCommand extends Command
{

    private const TOTAL_CODES = 500000;
    private const BATCH_SIZE = 500;
    private const CODE_LENGTH = 10;
    private const CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const TABLE_NAME = 'code';
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
        // Allocation mémoire a 1Go, voir si ça passe en prod, sinon il faudra baisser et limiter BATCH_SIZE a 50 ou 20
        ini_set('memory_limit', '1024M');

        $io = new SymfonyStyle($input, $output); // pour l'affichage
        // Permet de calculer le temps écoulé
        $stopwatch = new Stopwatch();
        // Lance le chrono
        $stopwatch->start('code_generation');

        $io->title(sprintf('Génération de %d codes uniques', self::TOTAL_CODES));
        $io->progressStart(self::TOTAL_CODES);

        $codesToInsert = [];
        $expiresAt = new \DateTimeImmutable('2026-06-11'); // date limite pour jouer le code/récupérer le gain
        $expiresAtFormat = $expiresAt->format('Y-m-d H:i:s');

        // Désactiver le log de Doctrine pour économiser encore plus de mémoire
        if (method_exists($this->connection->getConfiguration(), 'setSQLLogger')) {
            $this->connection->getConfiguration()->setSQLLogger(null);
        }

        for ($i = 1; $i <= self::TOTAL_CODES; $i++) {

            // 1. Accumuler les données dans un tableau PHP
            $codesToInsert[] = [
                'code' => $this->generateRandomCode(),
                'expires_at' => $expiresAtFormat,
            ];

            // 2. Traitement par Lots (Flush DBAL)
            if (($i % self::BATCH_SIZE) === 0) {
                $this->insertBatch($codesToInsert);
                $codesToInsert = []; // Vider le tableau
            }

            $io->progressAdvance();
        }

        // Exécuter le dernier lot incomplet
        if (!empty($codesToInsert)) {
            $this->insertBatch($codesToInsert);
        }

        $io->progressFinish();

        $event = $stopwatch->stop('code_generation');
        $io->success(sprintf(
            'Terminé ! %d codes créés en base de données en %.2f secondes.',
            self::TOTAL_CODES,
            $event->getDuration() / 1000
        ));

        return Command::SUCCESS;
    }

    /**
     * Insère un lot de codes en utilisant l'INSERT de Doctrine DBAL.
     * @throws Exception
     */
    private function insertBatch(array $batchData): void
    {
        // (Méthode plus rapide et moins de requêtes SQL)
        $values = [];
        $params = [];
        $i = 0;

        foreach ($batchData as $row) {
            $values[] = '(?, ?)';
            $params[] = $row['code'];
            $params[] = $row['expires_at'];
            $i++;
        }

        $query = sprintf(
            'INSERT INTO %s (code, expires_at) VALUES %s',
            self::TABLE_NAME,
            implode(', ', $values)
        );

        $this->connection->executeStatement($query, $params);
    }

    /**
     * Génère un code alphanumérique aléatoire.
     */
    private function generateRandomCode(): string
    {
        $charactersLength = strlen(self::CHARACTERS);
        $randomString = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            try {
                $randomString .= self::CHARACTERS[random_int(0, $charactersLength - 1)];
            } catch (RandomException $e) {
                throw new \RuntimeException("Erreur de génération de code.", 0, $e);
            }
        }
        return $randomString;
    }
}
