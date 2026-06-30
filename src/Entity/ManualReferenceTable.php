<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ManualReferenceTableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManualReferenceTableRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_manual_reference_table_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_manual_reference_table_status', columns: ['status'])]
#[ORM\Index(name: 'idx_manual_reference_table_position', columns: ['position'])]
class ManualReferenceTable
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 190)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: ManualPage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ManualPage $page = null;

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column]
    private array $columnsDefinition = [];

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /** @var Collection<int, ManualReferenceRow> */
    #[ORM\OneToMany(targetEntity: ManualReferenceRow::class, mappedBy: 'referenceTable', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $rows;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->rows = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = trim($title); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = trim($slug); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $description = $description !== null ? trim($description) : null; $this->description = $description !== '' ? $description : null; return $this; }
    public function getPage(): ?ManualPage { return $this->page; }
    public function setPage(?ManualPage $page): self { $this->page = $page; return $this; }
    /** @return array<int, array<string, mixed>> */
    public function getColumnsDefinition(): array { return $this->columnsDefinition; }
    /** @param array<int, array<string, mixed>> $columnsDefinition */
    public function setColumnsDefinition(array $columnsDefinition): self { $this->columnsDefinition = $columnsDefinition; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
    /** @return Collection<int, ManualReferenceRow> */
    public function getRows(): Collection { return $this->rows; }
}
