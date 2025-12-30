<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * @return Team[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('team')
            ->andWhere('team.state = :status')
            ->setParameter('status', $status)
            ->orderBy('team.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}