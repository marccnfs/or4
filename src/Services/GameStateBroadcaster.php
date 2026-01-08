<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\EscapeGame;
use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Services\TeamPinService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class GameStateBroadcaster
{
    public function __construct(
        private HubInterface $hub,
        private TeamRepository $teamRepository,
        private TeamPinService $teamPinService,
        private LoggerInterface $logger,
    ) {
    }

    public function publishStatus(EscapeGame $escapeGame): void
    {
        $payload = $this->buildStatusPayload($escapeGame);
        $this->publishUpdate($this->getStatusTopic($escapeGame), $payload);
    }

    public function publishScoreboard(EscapeGame $escapeGame): void
    {
        $payload = $this->buildScoreboardPayload($escapeGame);

        $this->publishUpdate($this->getScoreboardTopic($escapeGame), $payload);
    }

    public function publishTeamState(Team $team): void
    {
        $payload = $this->buildTeamPayload($team);

        $this->publishUpdate($this->getTeamTopic($team), $payload);
    }

    public function publishTeamProgressUpdated(Team $team): void
    {
        $payload = [
            'event' => 'team_progress_updated',
            'team' => $this->buildTeamPayload($team),
            'scoreboard' => $this->buildScoreboardPayload($team->getEscapeGame()),
        ];

        $this->publishUpdate($this->getTeamProgressTopic($team->getEscapeGame()), $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatusPayload(?EscapeGame $escapeGame): array
    {
        return [
            'status' => $escapeGame?->getStatus() ?? 'offline',
            'escape_name' => $escapeGame?->getName(),
            'updated_at' => $escapeGame?->getUpdatedAt()?->format('H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildScoreboardPayload(?EscapeGame $escapeGame): array
    {
        if ($escapeGame === null) {
            return [
                'status' => 'offline',
                'escape_name' => null,
                'total_steps' => 0,
                'winner' => null,
                'teams' => [],
                'updated_at' => null,
            ];
        }

        $teams = $this->teamRepository->findBy(['escapeGame' => $escapeGame], ['id' => 'ASC']);
        $totalSteps = $escapeGame->getSteps()->count();
        $payloadTeams = [];
        $latestUpdate = $escapeGame->getUpdatedAt();

        foreach ($teams as $team) {
            $validatedSteps = 0;
            $latestTeamUpdate = null;
            foreach ($team->getTeamStepProgresses() as $progress) {
                if ($progress->getState() === 'validated') {
                    $validatedSteps++;
                }
                $updatedAt = $progress->getUpdatedAt();
                if ($latestTeamUpdate === null || $updatedAt > $latestTeamUpdate) {
                    $latestTeamUpdate = $updatedAt;
                }
            }

            if ($latestTeamUpdate !== null && ($latestUpdate === null || $latestTeamUpdate > $latestUpdate)) {
                $latestUpdate = $latestTeamUpdate;
            }

            [$qrScanned, $qrTotal] = $this->getQrCounts($team);

            $payloadTeams[] = [
                'name' => $team->getName(),
                'code' => $team->getRegistrationCode(),
                'state' => $team->getState(),
                'score' => $team->getScore(),
                'validated_steps' => $validatedSteps,
                'last_update' => $latestTeamUpdate?->format('H:i'),
                'qr_scanned' => $qrScanned,
                'qr_total' => $qrTotal,
            ];
        }

        usort($payloadTeams, static function (array $left, array $right): int {
            if ($left['validated_steps'] === $right['validated_steps']) {
                return $left['name'] <=> $right['name'];
            }

            return $right['validated_steps'] <=> $left['validated_steps'];
        });

        $options = $escapeGame->getOptions();
        $winner = null;
        if ($escapeGame->getStatus() === 'finished' && !empty($options['winner_team_name'])) {
            $winner = [
                'name' => $options['winner_team_name'],
                'code' => $options['winner_team_code'] ?? null,
            ];
        }


        return [
            'status' => $escapeGame->getStatus(),
            'escape_name' => $escapeGame->getName(),
            'total_steps' => $totalSteps,
            'winner' => $winner,
            'teams' => $payloadTeams,
            'updated_at' => $latestUpdate?->format('H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function buildTeamPayload(Team $team): array
    {
        [$qrScanned, $qrTotal] = $this->getQrCounts($team);
        $pinPayload = $this->teamPinService->getCurrentPinPayload($team);
        $latestUpdate = $team->getEscapeGame()->getUpdatedAt();
        foreach ($team->getTeamStepProgresses() as $progress) {
            $updatedAt = $progress->getUpdatedAt();
            if ($latestUpdate === null || $updatedAt > $latestUpdate) {
                $latestUpdate = $updatedAt;
            }
        }
        foreach ($team->getTeamQrSequences() as $sequence) {
            $updatedAt = $sequence->getUpdatedAt();
            if ($latestUpdate === null || $updatedAt > $latestUpdate) {
                $latestUpdate = $updatedAt;
            }
        }

        return [
            'id' => $team->getId(),
            'escape_id' => $team->getEscapeGame()->getId(),
            'name' => $team->getName(),
            'code' => $team->getRegistrationCode(),
            'state' => $team->getState(),
            'score' => $team->getScore(),
            'qr_scanned' => $qrScanned,
            'qr_total' => $qrTotal,
            'pin' => $pinPayload['pin'],
            'pin_expires_at' => $pinPayload['expires_at'],
            'updated_at' => $latestUpdate?->format('H:i:s'),
        ];
    }

    private function getStatusTopic(EscapeGame $escapeGame): string
    {
        return sprintf('/escape/%d/status', $escapeGame->getId());
    }

    private function getScoreboardTopic(EscapeGame $escapeGame): string
    {
        return sprintf('/escape/%d/scoreboard', $escapeGame->getId());
    }

    private function getTeamTopic(Team $team): string
    {
        return sprintf('/escape/%d/team/%d/state', $team->getEscapeGame()->getId(), $team->getId());
    }

    private function getTeamProgressTopic(EscapeGame $escapeGame): string
    {
        return sprintf('/escape/%d/team_progress', $escapeGame->getId());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publishUpdate(string $topic, array $payload): void
    {
        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable $exception) {
            $this->logger->warning('Mercure publish failed.', [
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function getQrCounts(Team $team): array
    {
        $totalQr = $team->getTeamQrSequences()->count();
        $scannedQr = 0;
        foreach ($team->getTeamQrSequences() as $sequence) {
            if ($sequence->isValidated()) {
                $scannedQr++;
            }
        }

        return [$scannedQr, $totalQr];
    }
}