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
            ->select('u.gender AS gender, COUNT(u.id) AS total')
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
}
