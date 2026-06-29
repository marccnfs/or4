<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ManualPageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManualPageRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_manual_page_section_slug', columns: ['section_id', 'slug'])]
#[ORM\Index(name: 'idx_manual_page_position', columns: ['position'])]
#[ORM\Index(name: 'idx_manual_page_status', columns: ['status'])]
#[ORM\Index(name: 'idx_manual_page_type', columns: ['type'])]
class ManualPage
{
    public const TYPE_PAGE = 'page';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_MEMO = 'memo';
    public const TYPE_CHECKLIST = 'checklist';
    public const TYPE_REFERENTIEL = 'referentiel';
    public const TYPE_NAMING_RULE = 'naming-rule';
    public const TYPE_EQUIPMENT_SHEET = 'equipment-sheet';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ManualSection::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?ManualSection $section = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 190)]
    private string $slug = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_PAGE;

    #[ORM\Column(type: Types::TEXT)]
    private string $contentMarkdown = '';

    /** @var string[]|null */
    #[ORM\Column(nullable: true)]
    private ?array $tags = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    /** @var Collection<int, ManualPageVersion> */
    #[ORM\OneToMany(targetEntity: ManualPageVersion::class, mappedBy: 'page', orphanRemoval: false)]
    #[ORM\OrderBy(['versionNumber' => 'DESC'])]
    private Collection $versions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getSection(): ?ManualSection { return $this->section; }
    public function setSection(?ManualSection $section): self { $this->section = $section; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = trim($title); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = trim($slug); return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $summary): self { $summary = $summary !== null ? trim($summary) : null; $this->summary = $summary !== '' ? $summary : null; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getContentMarkdown(): string { return $this->contentMarkdown; }
    public function setContentMarkdown(string $contentMarkdown): self { $this->contentMarkdown = trim($contentMarkdown); return $this; }
    /** @return string[]|null */
    public function getTags(): ?array { return $this->tags; }
    /** @param string[]|null $tags */
    public function setTags(?array $tags): self { $this->tags = $tags !== null ? array_values(array_filter(array_map('trim', $tags))) : null; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getReviewedAt(): ?\DateTimeInterface { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTimeInterface $reviewedAt): self { $this->reviewedAt = $reviewedAt; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
    public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeInterface $publishedAt): self { $this->publishedAt = $publishedAt; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
    public function getUpdatedBy(): ?User { return $this->updatedBy; }
    public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }
    /** @return Collection<int, ManualPageVersion> */
    public function getVersions(): Collection { return $this->versions; }
}
