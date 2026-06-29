<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\TeamMembershipRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [new Get(), new GetCollection()],
    normalizationContext: ['groups' => ['team_membership:read']],
    security: "is_granted('ROLE_USER')",
)]
#[ORM\Entity(repositoryClass: TeamMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_team_member', columns: ['team_id', 'user_id'])]
class TeamMembership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['team_membership:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['team_membership:read'])]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['team_membership:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 30)]
    #[Groups(['team_membership:read'])]
    private string $role = 'member';

    #[ORM\Column]
    #[Groups(['team_membership:read'])]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['team_membership:read'])]
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
