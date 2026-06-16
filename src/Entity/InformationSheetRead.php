<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InformationSheetReadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InformationSheetReadRepository::class)]
#[ORM\Index(columns: ['read_at'], name: 'idx_information_sheet_read_read_at')]
class InformationSheetRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $agent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InformationSheet $sheet = null;

    #[ORM\Column]
    private \DateTimeImmutable $readAt;

    public function __construct()
    {
        $this->readAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?User
    {
        return $this->agent;
    }

    public function setAgent(User $agent): self
    {
        $this->agent = $agent;

        return $this;
    }

    public function getSheet(): ?InformationSheet
    {
        return $this->sheet;
    }

    public function setSheet(InformationSheet $sheet): self
    {
        $this->sheet = $sheet;

        return $this;
    }

    public function getReadAt(): \DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(\DateTimeImmutable $readAt): self
    {
        $this->readAt = $readAt;

        return $this;
    }
}
