<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Step
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EscapeGame $escapeGame = null;

    #[ORM\Column(length: 1)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $solution;

    #[ORM\Column(length: 1)]
    private string $letter;

    #[ORM\Column(name: 'order_number')]
    private int $orderNumber;

    #[ORM\OneToMany(mappedBy: 'step', targetEntity: TeamStepProgress::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $teamStepProgresses;

    public function __construct()
    {
        $this->teamStepProgresses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEscapeGame(): ?EscapeGame
    {
        return $this->escapeGame;
    }

    public function setEscapeGame(?EscapeGame $escapeGame): self
    {
        $this->escapeGame = $escapeGame;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSolution(): string
    {
        return $this->solution;
    }

    public function setSolution(string $solution): self
    {
        $this->solution = $solution;

        return $this;
    }

    public function getLetter(): string
    {
        return $this->letter;
    }

    public function setLetter(string $letter): self
    {
        $this->letter = $letter;

        return $this;
    }

    public function getOrderNumber(): int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(int $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /**
     * @return Collection<int, TeamStepProgress>
     */
    public function getTeamStepProgresses(): Collection
    {
        return $this->teamStepProgresses;
    }

    public function addTeamStepProgress(TeamStepProgress $teamStepProgress): self
    {
        if (!$this->teamStepProgresses->contains($teamStepProgress)) {
            $this->teamStepProgresses->add($teamStepProgress);
            $teamStepProgress->setStep($this);
        }

        return $this;
    }

    public function removeTeamStepProgress(TeamStepProgress $teamStepProgress): self
    {
        if ($this->teamStepProgresses->removeElement($teamStepProgress)) {
            if ($teamStepProgress->getStep() === $this) {
                $teamStepProgress->setStep(null);
            }
        }

        return $this;
    }
}