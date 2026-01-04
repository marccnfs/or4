<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\EscapeGame;
use App\Entity\Team;
use App\Entity\TeamQrSequence;
use App\Entity\TeamStepProgress;
use App\Services\GameStateBroadcaster;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;

class GameStateBroadcastSubscriber implements EventSubscriber
{
    /**
     * @var array<string, EscapeGame>
     */
    private array $pendingStatus = [];

    /**
     * @var array<string, EscapeGame>
     */
    private array $pendingScoreboards = [];

    /**
     * @var array<string, Team>
     */
    private array $pendingTeams = [];

    public function __construct(private GameStateBroadcaster $broadcaster)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        $this->collectEntities($unitOfWork->getScheduledEntityInsertions(), $unitOfWork);
        $this->collectEntities($unitOfWork->getScheduledEntityUpdates(), $unitOfWork);
        $this->collectEntities($unitOfWork->getScheduledEntityDeletions(), $unitOfWork);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingStatus === [] && $this->pendingScoreboards === [] && $this->pendingTeams === []) {
            return;
        }

        foreach ($this->pendingStatus as $escapeGame) {
            $this->broadcaster->publishStatus($escapeGame);
        }

        foreach ($this->pendingScoreboards as $escapeGame) {
            $this->broadcaster->publishScoreboard($escapeGame);
        }

        foreach ($this->pendingTeams as $team) {
            $this->broadcaster->publishTeamState($team);
        }

        $this->pendingStatus = [];
        $this->pendingScoreboards = [];
        $this->pendingTeams = [];
    }

    /**
     * @param array<int, object> $entities
     */
    private function collectEntities(array $entities, UnitOfWork $unitOfWork): void
    {
        foreach ($entities as $entity) {
            if ($entity instanceof EscapeGame) {
                $changeSet = $unitOfWork->getEntityChangeSet($entity);
                if ($changeSet === [] || isset($changeSet['status']) || isset($changeSet['updatedAt'])) {
                    $this->pendingStatus[$this->escapeKey($entity)] = $entity;
                }
                $this->pendingScoreboards[$this->escapeKey($entity)] = $entity;
                continue;
            }

            if ($entity instanceof Team) {
                $this->pendingTeams[$this->teamKey($entity)] = $entity;
                $escapeGame = $entity->getEscapeGame();
                $this->pendingScoreboards[$this->escapeKey($escapeGame)] = $escapeGame;
                continue;
            }

            if ($entity instanceof TeamStepProgress || $entity instanceof TeamQrSequence) {
                $team = $entity->getTeam();
                $this->pendingTeams[$this->teamKey($team)] = $team;
                $escapeGame = $team->getEscapeGame();
                $this->pendingScoreboards[$this->escapeKey($escapeGame)] = $escapeGame;
            }
        }
    }

    private function escapeKey(EscapeGame $escapeGame): string
    {
        return $escapeGame->getId() !== null
            ? (string) $escapeGame->getId()
            : (string) spl_object_id($escapeGame);
    }

    private function teamKey(Team $team): string
    {
        return $team->getId() !== null
            ? (string) $team->getId()
            : (string) spl_object_id($team);
    }
}