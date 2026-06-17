<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TeamInvitation> */
class TeamInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, TeamInvitation::class); }
    /** @return TeamInvitation[] */
    public function findRecentForTeam(Team $team): array { return $this->findBy(['team' => $team], ['createdAt' => 'DESC'], 10); }
}
