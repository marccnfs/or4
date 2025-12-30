<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'team')]
#[ORM\UniqueConstraint(name: 'uniq_team_registration_code', columns: ['registration_code'])]
#[UniqueEntity(fields: ['registrationCode'], message: 'Ce code d\'inscription est déjà utilisé.')]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teams')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EscapeGame $escapeGame = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $registrationCode;

    #[ORM\Column(length: 255)]
    private string $qrToken;

    #[ORM\Column(length: 50)]
    private string $state;

    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column(type: 'json')]
    private array $letterOrder = [];

    #[ORM\OneToMany(targetEntity: TeamStepProgress::class, mappedBy: 'team', cascade: ['persist'], orphanRemoval: true)]
    private Collection $teamStepProgresses;

    #[ORM\OneToMany(targetEntity: TeamQrSequence::class, mappedBy: 'team', cascade: ['persist'], orphanRemoval: true)]
    private Collection $teamQrSequences;

    public function __construct()
    {
        $this->teamStepProgresses = new ArrayCollection();
        $this->teamQrSequences = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getRegistrationCode(): string
    {
        return $this->registrationCode;
    }

    public function setRegistrationCode(string $registrationCode): self
    {
        $this->registrationCode = $registrationCode;

        return $this;
    }

    public function getQrToken(): string
    {
        return $this->qrToken;
    }

    public function setQrToken(string $qrToken): self
    {
        $this->qrToken = $qrToken;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getLetterOrder(): array
    {
        return $this->letterOrder;
    }

    public function setLetterOrder(array $letterOrder): self
    {
        $this->letterOrder = $letterOrder;

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
            $teamStepProgress->setTeam($this);
        }

        return $this;
    }

    public function removeTeamStepProgress(TeamStepProgress $teamStepProgress): self
    {
        if ($this->teamStepProgresses->removeElement($teamStepProgress)) {
            if ($teamStepProgress->getTeam() === $this) {
                $teamStepProgress->setTeam(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TeamQrSequence>
     */
    public function getTeamQrSequences(): Collection
    {
        return $this->teamQrSequences;
    }

    public function addTeamQrSequence(TeamQrSequence $teamQrSequence): self
    {
        if (!$this->teamQrSequences->contains($teamQrSequence)) {
            $this->teamQrSequences->add($teamQrSequence);
            $teamQrSequence->setTeam($this);
        }

        return $this;
    }

    public function removeTeamQrSequence(TeamQrSequence $teamQrSequence): self
    {
        if ($this->teamQrSequences->removeElement($teamQrSequence)) {
            if ($teamQrSequence->getTeam() === $this) {
                $teamQrSequence->setTeam(null);
            }
        }

        return $this;
    }
}