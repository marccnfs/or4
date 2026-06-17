<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TeamMessage> */
class TeamMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, TeamMessage::class); }
    /** @return TeamMessage[] */
    public function findForTeam(Team $team): array { return $this->createQueryBuilder('message')->addSelect('author')->join('message.author', 'author')->andWhere('message.team = :team')->setParameter('team', $team)->orderBy('message.createdAt', 'ASC')->getQuery()->getResult(); }
}
