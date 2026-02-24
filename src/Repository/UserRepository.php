<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Cherche un utilisateur (client ROLE_PARTICIPANT uniquement)
     * @return User[] Returns an array of User objects
     */
    public function searchCustomers($value): array
    {
        $qb = $this->createQueryBuilder('u');

        return $qb
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(u.firstName)', ':search'),
                    $qb->expr()->like('LOWER(u.lastName)', ':search'),
                    $qb->expr()->like('LOWER(u.email)', ':search'),
                )
            )
            ->andWhere("u.roles NOT LIKE :employee")
            ->andWhere("u.roles NOT LIKE :admin")
            ->setParameter('search', '%' . strtolower($value) . '%')
            ->setParameter('employee', '%ROLE_EMPLOYEE%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->orderBy('u.lastName', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    public function getWinnersStats(): array
    {
        // Répartition par genre
        $genderRows = $this->createQueryBuilder('u')
            ->select('u.gender AS gender, COUNT(DISTINCT u.id) AS total')
            ->join('u.wonCodes', 'c')
            ->where('c.isValidated = true')
            ->groupBy('u.gender')
            ->getQuery()
            ->getArrayResult();

        // Tranches d'âge
        $ageRows = $this->createQueryBuilder('u')
            ->select('u.birthDate AS birthDate')
            ->join('u.wonCodes', 'c')
            ->where('c.isValidated = true')
            ->andWhere('u.birthDate IS NOT NULL')
            ->groupBy('u.id')
            ->getQuery()
            ->getArrayResult();

        return [
            'gender'     => array_map(fn($r) => [
                'gender' => $r['gender'] instanceof \BackedEnum ? $r['gender']->value : ($r['gender'] ?? 'non renseigné'),
                'total'  => (int) $r['total'],
            ], $genderRows),
            'age_groups' => $this->buildAgeGroups($ageRows),
        ];
    }

    private function buildAgeGroups(array $rows): array
    {
        $groups = ['<18' => 0, '18-24' => 0, '25-34' => 0, '35-49' => 0, '50+' => 0];
        $now = new \DateTimeImmutable();

        foreach ($rows as $row) {
            $age = $row['birthDate']->diff($now)->y;
            $groups[match(true) {
                $age < 18  => '<18',
                $age <= 24 => '18-24',
                $age <= 34 => '25-34',
                $age <= 49 => '35-49',
                default    => '50+',
            }]++;
        }

        return array_map(
            fn($label, $total) => ['label' => $label, 'total' => $total],
            array_keys($groups),
            $groups
        );
    }

    /**
     * Requête pour export des données utilisateurs
     * @param string $filter
     * @return array
     */
    public function findForExport(string $filter = 'all'): array
    {
        $qb = $this->createQueryBuilder('u')
        ->select('u.email, u.firstName, u.lastName, u.newsletter')
            ->where('u.roles NOT LIKE :employee')
            ->andWhere("u.roles NOT LIKE :admin")
            ->setParameter('employee', '%ROLE_EMPLOYEE%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->orderBy('u.lastName', 'ASC');

        // On retourne uniquement les users ayant accepté les emails marketing
        if($filter === 'newsletter') {
            $qb->andWhere('u.newsletter = true');
        }

        // Utilisateurs avec au moins un lot gagné mais pas encore récupéré
        if($filter === 'unclaimed') {
            $qb->join('u.wonCodes', 'u')
                ->andWhere('c.isValidated = true')
                ->andWhere('c.isClaimed = true')
                ->groupBy('u.id');
        }
        // retourne le tableau de données brutes
        return $qb->getQuery()->getArrayResult();
    }
}
