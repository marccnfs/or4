<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamInvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamInvitationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_team_invitation_token', columns: ['token'])]
#[ORM\Index(columns: ['email'], name: 'idx_team_invitation_email')]
class TeamInvitation
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
    private ?User $invitedBy = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 100)]
    private string $token = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): ?int { return $this->id; }
    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(Team $team): self { $this->team = $team; return $this; }
    public function getInvitedBy(): ?User { return $this->invitedBy; }
    public function setInvitedBy(User $invitedBy): self { $this->invitedBy = $invitedBy; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }
    public function getToken(): string { return $this->token; }
    public function setToken(string $token): self { $this->token = $token; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }
    public function accept(): self { $this->acceptedAt = new \DateTimeImmutable(); return $this; }
    public function isAccepted(): bool { return $this->acceptedAt !== null; }
}
