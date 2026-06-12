<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InformationSheetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InformationSheetRepository::class)]
#[ORM\Index(columns: ['category'], name: 'idx_information_sheet_category')]
#[ORM\Index(columns: ['slug'], name: 'idx_information_sheet_slug')]
class InformationSheet
{
    public const CATEGORY_COMPRENDRE = 'comprendre';
    public const CATEGORY_JE_VEUX = 'je-veux';
    public const CATEGORY_BONNES_PRATIQUES = 'bonnes-pratiques';
    public const CATEGORY_FAQ = 'faq';

    public const CATEGORY_LABELS = [
        self::CATEGORY_COMPRENDRE => 'Comprendre',
        self::CATEGORY_JE_VEUX => 'Je veux...',
        self::CATEGORY_BONNES_PRATIQUES => 'Bonnes pratiques',
        self::CATEGORY_FAQ => 'FAQ',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(length: 220, nullable: true)]
    #[Assert\Length(max: 220)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::CATEGORY_COMPRENDRE, self::CATEGORY_JE_VEUX, self::CATEGORY_BONNES_PRATIQUES, self::CATEGORY_FAQ])]
    private string $category = self::CATEGORY_COMPRENDRE;

    #[ORM\Column(length: 140)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 140)]
    private string $thematic = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $contentMarkdown = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 220, nullable: true)]
    #[Assert\Length(max: 220)]
    private ?string $imageAlt = null;

    #[ORM\Column(length: 190, unique: true)]
    private string $slug = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $subtitle = $subtitle !== null ? trim($subtitle) : null;
        $this->subtitle = $subtitle !== '' ? $subtitle : null;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function getThematic(): string
    {
        return $this->thematic;
    }

    public function setThematic(string $thematic): self
    {
        $this->thematic = trim($thematic);

        return $this;
    }

    public function getContentMarkdown(): string
    {
        return $this->contentMarkdown;
    }

    public function setContentMarkdown(string $contentMarkdown): self
    {
        $this->contentMarkdown = trim($contentMarkdown);

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): self
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    public function setImageAlt(?string $imageAlt): self
    {
        $imageAlt = $imageAlt !== null ? trim($imageAlt) : null;
        $this->imageAlt = $imageAlt !== '' ? $imageAlt : null;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
