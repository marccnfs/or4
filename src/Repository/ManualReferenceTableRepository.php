<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ManualPage;
use App\Entity\ManualReferenceTable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ManualReferenceTable> */
class ManualReferenceTableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ManualReferenceTable::class); }

    public function findPublishedForPage(ManualPage $page): ?ManualReferenceTable
    {
        return $this->createQueryBuilder('referenceTable')
            ->addSelect('rows')
            ->leftJoin('referenceTable.rows', 'rows', 'WITH', 'rows.isActive = :active')
            ->andWhere('referenceTable.page = :page')
            ->andWhere('referenceTable.status = :status')
            ->setParameter('page', $page)
            ->setParameter('status', ManualReferenceTable::STATUS_PUBLISHED)
            ->setParameter('active', true)
            ->orderBy('referenceTable.position', 'ASC')
            ->addOrderBy('rows.position', 'ASC')
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }
}
