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

    public function getWinnersGenderStats(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.gender AS gender, COUNT(u.id) AS total')
            ->join('u.tickets', 't')
            ->join('t.code', 'c')
            ->where('c.isValidated = true')
            ->groupBy('u.gender')
            ->getQuery()
            ->getArrayResult();

        // Normalise les valeurs de l'enum en string lisible
        return array_map(fn($row) => [
            'gender' => $row['gender'] instanceof \BackedEnum ? $row['gender']->value : $row['gender'],
            'total'  => (int) $row['total'],
        ], $rows);
    }
}
