<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\EscapeGame;
use App\Entity\Team;
use App\Repository\TeamRepository;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class GameStateBroadcaster
{
    public function __construct(
        private HubInterface $hub,
        private TeamRepository $teamRepository,
    ) {
    }

    public function publishStatus(EscapeGame $escapeGame): void
    {
        $payload = $this->buildStatusPayload($escapeGame);

        $this->hub->publish(new Update(
            $this->getStatusTopic($escapeGame),
            json_encode($payload),
        ));
    }

    public function publishScoreboard(EscapeGame $escapeGame): void
    {
        $payload = $this->buildScoreboardPayload($escapeGame);

        $this->hub->publish(new Update(
            $this->getScoreboardTopic($escapeGame),
            json_encode($payload),
        ));
    }

    public function publishTeamState(Team $team): void
    {
        $payload = $this->buildTeamPayload($team);

        $this->hub->publish(new Update(
            $this->getTeamTopic($team),
            json_encode($payload),
        ));
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

            if ($latestTeamUpdate !== null && $latestTeamUpdate > $latestUpdate) {
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
        if (!empty($options['winner_team_name'])) {
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
            'updated_at' => $latestUpdate->format('H:i'),
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

        $latestUpdate = $team->getEscapeGame()->getUpdatedAt();
        foreach ($team->getTeamStepProgresses() as $progress) {
            $updatedAt = $progress->getUpdatedAt();
            if ($updatedAt > $latestUpdate) {
                $latestUpdate = $updatedAt;
            }
        }
        foreach ($team->getTeamQrSequences() as $sequence) {
            $updatedAt = $sequence->getUpdatedAt();
            if ($updatedAt > $latestUpdate) {
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
            'updated_at' => $latestUpdate->format('H:i'),
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