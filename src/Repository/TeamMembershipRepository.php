<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamMembership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TeamMembership> */
class TeamMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, TeamMembership::class); }
    public function isMember(Team $team, User $user): bool { return $this->findOneBy(['team' => $team, 'user' => $user]) !== null; }
    /** @return TeamMembership[] */
    public function findForUser(User $user): array { return $this->createQueryBuilder('m')->addSelect('team', 'escape')->join('m.team', 'team')->join('team.escapeGame', 'escape')->andWhere('m.user = :user')->setParameter('user', $user)->orderBy('m.joinedAt', 'DESC')->getQuery()->getResult(); }
    /** @return TeamMembership[] */
    public function findForTeam(Team $team): array { return $this->createQueryBuilder('m')->addSelect('user')->join('m.user', 'user')->andWhere('m.team = :team')->setParameter('team', $team)->orderBy('m.joinedAt', 'ASC')->getQuery()->getResult(); }
}
