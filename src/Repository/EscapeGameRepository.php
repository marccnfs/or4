<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EscapeGame;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EscapeGame>
 */
class EscapeGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EscapeGame::class);
    }

    /**
     * @return EscapeGame[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('escape_game')
            ->andWhere('escape_game.status = :status')
            ->setParameter('status', $status)
            ->orderBy('escape_game.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatest(): ?EscapeGame
    {
        return $this->createQueryBuilder('escape_game')
            ->orderBy('escape_game.updatedAt', 'DESC')
            ->addOrderBy('escape_game.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $statuses
     */
    public function findLatestByStatuses(array $statuses): ?EscapeGame
    {
        if ($statuses === []) {
            return null;
        }

        return $this->createQueryBuilder('escape_game')
            ->andWhere('escape_game.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->orderBy('escape_game.updatedAt', 'DESC')
            ->addOrderBy('escape_game.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}