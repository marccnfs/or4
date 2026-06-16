<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InformationSheetRead;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InformationSheetRead> */
class InformationSheetReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationSheetRead::class);
    }

    /** @return InformationSheetRead[] */
    public function findRecentForAgent(User $agent, int $limit = 20): array
    {
        return $this->createQueryBuilder('read')
            ->addSelect('sheet')
            ->join('read.sheet', 'sheet')
            ->andWhere('read.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('read.readAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
