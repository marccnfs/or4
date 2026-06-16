<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InformationSheetWorkspaceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InformationSheetWorkspaceRepository::class)]
#[ORM\Index(columns: ['created_at'], name: 'idx_information_sheet_workspace_created_at')]
class InformationSheetWorkspace
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

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(length: 140)]
    private string $thematicSnapshot = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $personalNotes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $questions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalElements = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromSheet(User $agent, InformationSheet $sheet): self
    {
        return (new self())
            ->setAgent($agent)
            ->setSheet($sheet)
            ->setTitle('Dossier de travail · ' . $sheet->getTitle())
            ->setThematicSnapshot($sheet->getThematic());
    }

    public function getId(): ?int { return $this->id; }
    public function getAgent(): ?User { return $this->agent; }
    public function setAgent(User $agent): self { $this->agent = $agent; return $this; }
    public function getSheet(): ?InformationSheet { return $this->sheet; }
    public function setSheet(InformationSheet $sheet): self { $this->sheet = $sheet; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = trim($title); return $this; }
    public function getThematicSnapshot(): string { return $this->thematicSnapshot; }
    public function setThematicSnapshot(string $thematicSnapshot): self { $this->thematicSnapshot = trim($thematicSnapshot); return $this; }
    public function getPersonalNotes(): ?string { return $this->personalNotes; }
    public function setPersonalNotes(?string $personalNotes): self { $this->personalNotes = $this->cleanNullableText($personalNotes); return $this; }
    public function getQuestions(): ?string { return $this->questions; }
    public function setQuestions(?string $questions): self { $this->questions = $this->cleanNullableText($questions); return $this; }
    public function getAdditionalElements(): ?string { return $this->additionalElements; }
    public function setAdditionalElements(?string $additionalElements): self { $this->additionalElements = $this->cleanNullableText($additionalElements); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    private function cleanNullableText(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
