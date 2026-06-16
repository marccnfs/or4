<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InformationSheetWorkspace;
use App\Entity\InformationSheetWorkspaceMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InformationSheetWorkspaceMessage> */
class InformationSheetWorkspaceMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationSheetWorkspaceMessage::class);
    }

    /** @return InformationSheetWorkspaceMessage[] */
    public function findForWorkspace(InformationSheetWorkspace $workspace): array
    {
        return $this->createQueryBuilder('message')
            ->addSelect('author')
            ->join('message.author', 'author')
            ->andWhere('message.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->orderBy('message.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
