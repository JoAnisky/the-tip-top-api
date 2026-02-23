<?php

namespace App\Repository;

use App\Entity\Code;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Code>
 */
class CodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Code::class);
    }

        /**
         * @return Code[] Returns an array of Code objects
         */
        public function getValidatedCodes($user): array
        {
            return $this->createQueryBuilder('c')
                ->leftJoin('c.gain', 'g')
                ->addSelect('g')
                ->where('c.winner = :user')
                ->andWhere('c.isValidated = true')
                ->setParameter('user', $user)
                ->orderBy('c.isClaimed', 'ASC')   // false (0) en premier = non remis en haut
                ->addOrderBy('c.validatedOn', 'DESC')
                ->getQuery()
                ->getResult();
        }

    //    public function findOneBySomeField($value): ?Code
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
