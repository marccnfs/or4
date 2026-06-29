<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ManualPageVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ManualPageVersion> */
class ManualPageVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManualPageVersion::class);
    }
}
