<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InformationSheet;
use App\Entity\InformationSheetWorkspace;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InformationSheetWorkspace> */
class InformationSheetWorkspaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationSheetWorkspace::class);
    }

    /** @return InformationSheetWorkspace[] */
    public function findForAgent(User $agent): array
    {
        return $this->createQueryBuilder('workspace')
            ->addSelect('sheet')
            ->join('workspace.sheet', 'sheet')
            ->andWhere('workspace.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('workspace.updatedAt', 'DESC')
            ->addOrderBy('workspace.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForAgentAndSheet(User $agent, InformationSheet $sheet): ?InformationSheetWorkspace
    {
        return $this->findOneBy(['agent' => $agent, 'sheet' => $sheet]);
    }
}
