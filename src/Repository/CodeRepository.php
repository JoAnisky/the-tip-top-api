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

    public function getTicketStats(): array
    {
        $total = $this->count([]);
        $validated = $this->count(['isValidated' => true]);

        return [
            'total'             => $total,
            'validated'         => $validated,
            'participation_rate' => $total > 0 ? round($validated / $total * 100, 1) : 0,
        ];
    }

    public function getGainStats(): array
    {
        return $this->createQueryBuilder('c')
            ->select('g.name AS gain_name, COUNT(c.id) AS total')
            ->join('c.gain', 'g')
            ->where('c.isValidated = true')
            ->groupBy('g.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getClaimStats(): array
    {
        $won    = $this->count(['isValidated' => true]);
        $claimed = $this->count(['isClaimed' => true]);

        return [
            'won'          => $won,
            'claimed'      => $claimed,
            'claim_rate'   => $won > 0 ? round($claimed / $won * 100, 1) : 0,
        ];
    }
}
