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
        $total     = $this->count([]);                        // tickets fournis
        $validated = $this->count(['isValidated' => true]);   // tickets utilisés (= lots gagnés)
        $claimed   = $this->count(['isClaimed'   => true]);   // lots remis physiquement

        return [
            'total'              => $total,
            'used'               => $validated,
            'won'                => $validated,   // même valeur, deux labels différents côté front
            'claimed'            => $claimed,
            'participation_rate' => $total > 0 ? round($validated / $total * 100, 1) : 0,
            'claim_rate'         => $validated > 0 ? round($claimed / $validated * 100, 1) : 0,
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

}
