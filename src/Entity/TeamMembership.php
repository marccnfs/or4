<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_team_member', columns: ['team_id', 'user_id'])]
class TeamMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 30)]
    private string $role = 'member';

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $blocked = false;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(Team $team): self { $this->team = $team; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }
    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }
    public function setJoinedAt(\DateTimeImmutable $joinedAt): self { $this->joinedAt = $joinedAt; return $this; }
    public function isBlocked(): bool { return $this->blocked; }
    public function setBlocked(bool $blocked): self { $this->blocked = $blocked; return $this; }
}
