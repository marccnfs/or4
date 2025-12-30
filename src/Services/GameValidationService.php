<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Step;
use App\Entity\Team;
use App\Entity\TeamQrSequence;
use App\Entity\TeamStepProgress;
use Doctrine\ORM\EntityManagerInterface;

class GameValidationService
{
    private const SCORE_INCREMENT = 1;
    private const STATE_PENDING = 'pending';
    private const STATE_IN_PROGRESS = 'in_progress';
    private const STATE_VALIDATED = 'validated';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function validateStep(Team $team, Step $step, array $payload): array
    {
        $type = strtoupper($step->getType());

        return match ($type) {
            'A', 'B', 'C', 'D' => $this->validateLetterStep($team, $step, $payload),
            'E' => $this->validateQrStep($team, $step, $payload),
            'F' => $this->validateFinalStep($team, $step, $payload),
            default => [
                'valid' => false,
                'message' => 'Étape inconnue.',
                'updated' => false,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLetterStep(Team $team, Step $step, array $payload): array
    {
        $letter = strtoupper(trim((string) ($payload['letter'] ?? '')));
        if ($letter === '') {
            return [
                'valid' => false,
                'message' => 'Lettre manquante.',
                'updated' => false,
            ];
        }

        $expected = strtoupper(trim($step->getSolution()));
        if ($letter !== $expected) {
            return [
                'valid' => false,
                'message' => 'Lettre incorrecte.',
                'updated' => false,
            ];
        }

        $progress = $this->getOrCreateProgress($team, $step);
        $wasValidated = $progress->getState() === self::STATE_VALIDATED;
        $this->markProgressValidated($progress, $letter);
        $this->incrementScore($team, !$wasValidated);

        return [
            'valid' => true,
            'message' => 'Bonne lettre.',
            'updated' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQrStep(Team $team, Step $step, array $payload): array
    {
        $code = trim((string) ($payload['code'] ?? ''));
        if ($code === '') {
            return [
                'valid' => false,
                'message' => 'Code QR manquant.',
                'updated' => false,
            ];
        }

        $nextSequence = $this->getNextQrSequence($team);
        if ($nextSequence === null) {
            return [
                'valid' => false,
                'message' => 'Séquence terminée.',
                'completed' => true,
                'updated' => false,
            ];
        }

        if ($code !== $nextSequence->getQrCode()) {
            return [
                'valid' => false,
                'message' => 'QR incorrect.',
                'nextHint' => $nextSequence->getHint(),
                'completed' => false,
                'updated' => false,
            ];
        }

        $nextSequence->setValidated(true);

        $progress = $this->getOrCreateProgress($team, $step);
        $completed = $this->isQrSequenceCompleted($team);
        if ($completed) {
            $this->markProgressValidated($progress, $step->getLetter());
        } else {
            $progress->setState(self::STATE_IN_PROGRESS);
            $progress->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->incrementScore($team, true);

        return [
            'valid' => true,
            'message' => $completed ? 'Séquence terminée.' : 'QR validé.',
            'nextHint' => $this->getNextQrHint($team),
            'completed' => $completed,
            'updated' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFinalStep(Team $team, Step $step, array $payload): array
    {
        $combination = strtoupper(trim((string) ($payload['combination'] ?? '')));
        if ($combination === '') {
            return [
                'valid' => false,
                'message' => 'Combinaison manquante.',
                'updated' => false,
            ];
        }

        $expected = $this->buildFinalCombination($team);
        if ($combination !== $expected) {
            return [
                'valid' => false,
                'message' => 'Combinaison incorrecte.',
                'updated' => false,
            ];
        }

        $progress = $this->getOrCreateProgress($team, $step);
        $wasValidated = $progress->getState() === self::STATE_VALIDATED;
        $this->markProgressValidated($progress, null);
        $this->incrementScore($team, !$wasValidated);

        return [
            'valid' => true,
            'message' => 'Bonne combinaison.',
            'updated' => true,
        ];
    }

    private function markProgressValidated(TeamStepProgress $progress, ?string $validatedLetter): void
    {
        $progress->setState(self::STATE_VALIDATED);
        $progress->setValidatedLetter($validatedLetter);
        $progress->setUpdatedAt(new \DateTimeImmutable());
    }

    private function incrementScore(Team $team, bool $shouldIncrement): void
    {
        if (!$shouldIncrement) {
            return;
        }

        $team->setScore($team->getScore() + self::SCORE_INCREMENT);
    }

    private function getOrCreateProgress(Team $team, Step $step): TeamStepProgress
    {
        $progress = $this->entityManager->getRepository(TeamStepProgress::class)->findOneBy([
            'team' => $team,
            'step' => $step,
        ]);

        if ($progress) {
            return $progress;
        }

        $progress = new TeamStepProgress();
        $progress->setTeam($team);
        $progress->setStep($step);
        $progress->setState(self::STATE_PENDING);
        $this->entityManager->persist($progress);

        return $progress;
    }

    private function getNextQrSequence(Team $team): ?TeamQrSequence
    {
        $sequences = $team->getTeamQrSequences()->toArray();
        usort($sequences, static function (TeamQrSequence $left, TeamQrSequence $right): int {
            return $left->getOrderNumber() <=> $right->getOrderNumber();
        });

        foreach ($sequences as $sequence) {
            if (!$sequence->isValidated()) {
                return $sequence;
            }
        }

        return null;
    }

    private function isQrSequenceCompleted(Team $team): bool
    {
        foreach ($team->getTeamQrSequences() as $sequence) {
            if (!$sequence->isValidated()) {
                return false;
            }
        }

        return true;
    }

    private function getNextQrHint(Team $team): ?string
    {
        $next = $this->getNextQrSequence($team);

        return $next?->getHint();
    }

    private function buildFinalCombination(Team $team): string
    {
        $letters = $team->getLetterOrder();

        if ($letters === []) {
            $steps = $team->getEscapeGame()->getSteps()->toArray();
            usort($steps, static function (Step $left, Step $right): int {
                return $left->getOrderNumber() <=> $right->getOrderNumber();
            });

            foreach ($steps as $step) {
                if (in_array(strtoupper($step->getType()), ['A', 'B', 'C', 'D', 'E'], true)) {
                    $letters[] = $step->getLetter();
                }
            }
        }

        $letters = array_map(static fn (string $letter): string => strtoupper($letter), $letters);

        return implode('', $letters);
    }
}