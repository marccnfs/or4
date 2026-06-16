<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InformationSheetWorkspace;
use App\Entity\InformationSheetWorkspaceAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InformationSheetWorkspaceAttachment> */
class InformationSheetWorkspaceAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InformationSheetWorkspaceAttachment::class);
    }

    /** @return InformationSheetWorkspaceAttachment[] */
    public function findForWorkspace(InformationSheetWorkspace $workspace): array
    {
        return $this->findBy(['workspace' => $workspace], ['uploadedAt' => 'DESC']);
    }
}
