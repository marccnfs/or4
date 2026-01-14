<?php

namespace App\Controller\Escapegame;

use App\Entity\EscapeGame;
use App\Entity\TeamQrScan;
use App\Repository\EscapeGameRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GamePilotController extends AbstractController
{
    #[Route('/game/pilot', name: 'game_pilot', methods: ['GET'])]
    public function index(Request $request, EscapeGameRepository $escapeGameRepository): Response
    {
        $escapeId = $request->query->getInt('escape_id', 0);
        $escapeGame = $escapeId > 0
            ? $escapeGameRepository->find($escapeId)
            : $escapeGameRepository->findLatest();

        return $this->render('admin/escape/pilot.html.twig', [
            'escape_game' => $escapeGame,
            'join_url' => $this->generateUrl('game_join', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'home_url' => $this->generateUrl('game_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/game/pilot/start', name: 'game_pilot_start', methods: ['POST'])]
    public function start(Request $request, EscapeGameRepository $escapeGameRepository, EntityManagerInterface $entityManager): Response
    {
        $escapeGame = $this->resolveEscapeGame($request, $escapeGameRepository);
        if ($escapeGame === null) {
            $this->addFlash('error', 'Escape introuvable.');
            return $this->redirectToRoute('game_pilot');
        }

        if (!$this->isCsrfTokenValid('game_pilot_' . $escapeGame->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('game_pilot');
        }

        $this->clearWinnerOptions($escapeGame);
        $escapeGame->setStatus('active');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Le jeu est lancé.');

        return $this->redirectToRoute('game_pilot');
    }

    #[Route('/game/pilot/stop', name: 'game_pilot_stop', methods: ['POST'])]
    public function stop(Request $request, EscapeGameRepository $escapeGameRepository, EntityManagerInterface $entityManager): Response
    {
        $escapeGame = $this->resolveEscapeGame($request, $escapeGameRepository);
        if ($escapeGame === null) {
            $this->addFlash('error', 'Escape introuvable.');
            return $this->redirectToRoute('game_pilot');
        }

        if (!$this->isCsrfTokenValid('game_pilot_' . $escapeGame->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('game_pilot');
        }

        $escapeGame->setStatus('waiting');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Le jeu est en attente.');

        return $this->redirectToRoute('game_pilot');
    }

    #[Route('/game/pilot/offline', name: 'game_pilot_offline', methods: ['POST'])]
    public function offline(Request $request, EscapeGameRepository $escapeGameRepository, EntityManagerInterface $entityManager): Response
    {
        $escapeGame = $this->resolveEscapeGame($request, $escapeGameRepository);
        if ($escapeGame === null) {
            $this->addFlash('error', 'Escape introuvable.');
            return $this->redirectToRoute('game_pilot');
        }

        if (!$this->isCsrfTokenValid('game_pilot_' . $escapeGame->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('game_pilot');
        }


        $escapeGame->setStatus('offline');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'L\'escape est hors ligne.');

        return $this->redirectToRoute('game_pilot');
    }

    #[Route('/game/pilot/finish', name: 'game_pilot_finish', methods: ['POST'])]
    public function finish(
        Request $request,
        EscapeGameRepository $escapeGameRepository,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $escapeGame = $this->resolveEscapeGame($request, $escapeGameRepository);
        if ($escapeGame === null) {
            $this->addFlash('error', 'Escape introuvable.');
            return $this->redirectToRoute('game_pilot');
        }

        if (!$this->isCsrfTokenValid('game_pilot_' . $escapeGame->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('game_pilot');
        }

        $winnerTeamId = $request->request->getInt('winner_team_id');
        if ($winnerTeamId > 0) {
            $winnerTeam = $teamRepository->find($winnerTeamId);
            if ($winnerTeam !== null && $winnerTeam->getEscapeGame()->getId() === $escapeGame->getId()) {
                $options = $escapeGame->getOptions();
                $options['winner_team_id'] = $winnerTeam->getId();
                $options['winner_team_name'] = $winnerTeam->getName();
                $options['winner_team_code'] = $winnerTeam->getRegistrationCode();
                $escapeGame->setOptions($options);
            }
        }

        $escapeGame->setStatus('finished');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'La partie est terminée.');

        return $this->redirectToRoute('game_pilot');
    }

    #[Route('/game/pilot/reset', name: 'game_pilot_reset', methods: ['POST'])]
    public function reset(Request $request, EscapeGameRepository $escapeGameRepository, EntityManagerInterface $entityManager): Response
    {
        $escapeGame = $this->resolveEscapeGame($request, $escapeGameRepository);
        if ($escapeGame === null) {
            $this->addFlash('error', 'Escape introuvable.');
            return $this->redirectToRoute('game_pilot');
        }

        if (!$this->isCsrfTokenValid('game_pilot_' . $escapeGame->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('game_pilot');
        }

        $teams = $escapeGame->getTeams();
        if ($teams->count() > 0) {
            $entityManager->createQuery(
                'DELETE FROM ' . TeamQrScan::class . ' scan WHERE scan.team IN (:teams)'
            )->setParameter('teams', $teams)->execute();
        }

        foreach ($teams as $team) {
            $entityManager->remove($team);
        }

        $this->clearWinnerOptions($escapeGame);
        $escapeGame->setStatus('waiting');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Les équipes ont été réinitialisées.');

        return $this->redirectToRoute('game_pilot');
    }

    #[Route('/game/pilot/teams', name: 'game_pilot_teams', methods: ['GET'])]
    public function teams(EscapeGameRepository $escapeGameRepository, TeamRepository $teamRepository): JsonResponse
    {
        $escapeGame = $escapeGameRepository->findLatest();
        if ($escapeGame === null) {
            return $this->json([
                'status' => 'offline',
                'teams' => [],
                'count' => 0,
            ]);
        }

        $teams = $teamRepository->findBy(['escapeGame' => $escapeGame], ['id' => 'ASC']);
        $payload = array_map(static function ($team): array {
            $totalQr = $team->getTeamQrSequences()->count();
            $scannedQr = 0;
            foreach ($team->getTeamQrSequences() as $sequence) {
                if ($sequence->isValidated()) {
                    $scannedQr++;
                }
            }
            return [
                'name' => $team->getName(),
                'code' => $team->getRegistrationCode(),
                'state' => $team->getState(),
                'score' => $team->getScore(),
                'qr_scanned' => $scannedQr,
                'qr_total' => $totalQr,
            ];
        }, $teams);

        return $this->json([
            'status' => $escapeGame->getStatus(),
            'teams' => $payload,
            'count' => count($payload),
            'updated_at' => $escapeGame->getUpdatedAt()->format('H:i'),
        ]);
    }

    #[Route('/game/pilot/teams/stream', name: 'game_pilot_teams_stream', methods: ['GET'])]
    public function teamsStream(EscapeGameRepository $escapeGameRepository, TeamRepository $teamRepository): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($escapeGameRepository, $teamRepository): void {
            ignore_user_abort(true);
            $start = time();
            $lastPayload = null;

            while (!connection_aborted() && (time() - $start) < 20) {
                $escapeGame = $escapeGameRepository->findLatest();
                if ($escapeGame === null) {
                    $payload = [
                        'status' => 'offline',
                        'teams' => [],
                        'count' => 0,
                        'updated_at' => null,
                    ];
                } else {
                    $teams = $teamRepository->findBy(['escapeGame' => $escapeGame], ['id' => 'ASC']);
                    $payloadTeams = array_map(static function ($team): array {
                        $totalQr = $team->getTeamQrSequences()->count();
                        $scannedQr = 0;
                        foreach ($team->getTeamQrSequences() as $sequence) {
                            if ($sequence->isValidated()) {
                                $scannedQr++;
                            }
                        }
                        return [
                            'name' => $team->getName(),
                            'code' => $team->getRegistrationCode(),
                            'state' => $team->getState(),
                            'score' => $team->getScore(),
                            'qr_scanned' => $scannedQr,
                            'qr_total' => $totalQr,
                        ];
                    }, $teams);

                    $payload = [
                        'status' => $escapeGame->getStatus(),
                        'teams' => $payloadTeams,
                        'count' => count($payloadTeams),
                        'updated_at' => $escapeGame->getUpdatedAt()->format('H:i'),
                    ];
                }

                $encoded = json_encode($payload);
                if ($encoded !== $lastPayload) {
                    echo "event: pilot_teams\n";
                    echo 'data: ' . $encoded . "\n\n";
                    $lastPayload = $encoded;
                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                }

                sleep(1);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function clearWinnerOptions(EscapeGame $escapeGame): void
    {
        $options = $escapeGame->getOptions();
        unset(
            $options['winner_team_id'],
            $options['winner_team_name'],
            $options['winner_team_code'],
        );
        $escapeGame->setOptions($options);
    }


    private function resolveEscapeGame(Request $request, EscapeGameRepository $escapeGameRepository): ?EscapeGame
    {
        $escapeId = $request->request->get('escape_id');
        if ($escapeId) {
            return $escapeGameRepository->find((int) $escapeId);
        }

        return $escapeGameRepository->findLatest();
    }

}