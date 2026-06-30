<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ManualReferenceRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ManualReferenceRowRepository::class)]
#[ORM\Index(name: 'idx_manual_reference_row_position', columns: ['position'])]
#[ORM\Index(name: 'idx_manual_reference_row_active', columns: ['is_active'])]
class ManualReferenceRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ManualReferenceTable::class, inversedBy: 'rows')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ManualReferenceTable $referenceTable = null;

    #[ORM\Column]
    private array $data = [];

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getReferenceTable(): ?ManualReferenceTable { return $this->referenceTable; }
    public function setReferenceTable(?ManualReferenceTable $referenceTable): self { $this->referenceTable = $referenceTable; return $this; }
    public function getData(): array { return $this->data; }
    public function setData(array $data): self { $this->data = $data; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
