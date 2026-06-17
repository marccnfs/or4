<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamMessageRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_team_message_created_at')]
class TeamMessage
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
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $imageOriginalName = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(Team $team): self { $this->team = $team; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(User $author): self { $this->author = $author; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $content = $content !== null ? trim($content) : null; $this->content = $content !== '' ? $content : null; return $this; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }
    public function getImageOriginalName(): ?string { return $this->imageOriginalName; }
    public function setImageOriginalName(?string $imageOriginalName): self { $this->imageOriginalName = $imageOriginalName; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
