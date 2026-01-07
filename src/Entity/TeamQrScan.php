<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'team_qr_scan')]
class TeamQrScan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TeamQrSequence $qrSequence = null;

    #[ORM\Column]
    private \DateTimeImmutable $scannedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $scannedByUserAgent = null;

    public function __construct()
    {
        $this->scannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    public function getQrSequence(): ?TeamQrSequence
    {
        return $this->qrSequence;
    }

    public function setQrSequence(?TeamQrSequence $qrSequence): self
    {
        $this->qrSequence = $qrSequence;

        return $this;
    }

    public function getScannedAt(): \DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function setScannedAt(\DateTimeImmutable $scannedAt): self
    {
        $this->scannedAt = $scannedAt;

        return $this;
    }

    public function getScannedByUserAgent(): ?string
    {
        return $this->scannedByUserAgent;
    }

    public function setScannedByUserAgent(?string $scannedByUserAgent): self
    {
        $this->scannedByUserAgent = $scannedByUserAgent;

        return $this;
    }
}