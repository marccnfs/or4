<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InformationSheetWorkspaceAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InformationSheetWorkspaceAttachmentRepository::class)]
class InformationSheetWorkspaceAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?InformationSheetWorkspace $workspace = null;

    #[ORM\Column(length: 255)]
    private string $path = '';

    #[ORM\Column(length: 180)]
    private string $originalName = '';

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getWorkspace(): ?InformationSheetWorkspace { return $this->workspace; }
    public function setWorkspace(InformationSheetWorkspace $workspace): self { $this->workspace = $workspace; return $this; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self { $this->path = $path; return $this; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $originalName): self { $this->originalName = mb_substr(trim($originalName), 0, 180); return $this; }
    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self { $this->uploadedAt = $uploadedAt; return $this; }
}
