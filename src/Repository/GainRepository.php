<?php

namespace App\Repository;

use App\Entity\Gain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gain>
 */
class GainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gain::class);
    }

//    /**
//     * @return Gain[] Returns an array of Gain objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('g.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Gain
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
    public function findActiveGains(): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.allocatedQuantity < g.maxQuantity')
            ->andWhere('g.allocationDate IS NULL')
            ->orderBy('g.probability', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
