<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ManualPageVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManualPageVersionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_manual_page_version_number', columns: ['page_id', 'version_number'])]
#[ORM\Index(name: 'idx_manual_page_version_created_at', columns: ['created_at'])]
class ManualPageVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ManualPage::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ManualPage $page = null;

    #[ORM\Column(length: 180)]
    private string $titleSnapshot = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $contentMarkdownSnapshot = '';

    #[ORM\Column]
    private int $versionNumber = 1;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $changeSummary = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPage(): ?ManualPage { return $this->page; }
    public function setPage(?ManualPage $page): self { $this->page = $page; return $this; }
    public function getTitleSnapshot(): string { return $this->titleSnapshot; }
    public function setTitleSnapshot(string $titleSnapshot): self { $this->titleSnapshot = trim($titleSnapshot); return $this; }
    public function getContentMarkdownSnapshot(): string { return $this->contentMarkdownSnapshot; }
    public function setContentMarkdownSnapshot(string $contentMarkdownSnapshot): self { $this->contentMarkdownSnapshot = trim($contentMarkdownSnapshot); return $this; }
    public function getVersionNumber(): int { return $this->versionNumber; }
    public function setVersionNumber(int $versionNumber): self { $this->versionNumber = $versionNumber; return $this; }
    public function getChangeSummary(): ?string { return $this->changeSummary; }
    public function setChangeSummary(?string $changeSummary): self { $changeSummary = $changeSummary !== null ? trim($changeSummary) : null; $this->changeSummary = $changeSummary !== '' ? $changeSummary : null; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
}
