<?php

namespace App\Controller\Escapegame;

use App\Entity\EscapeGame;
use App\Entity\Step;
use App\Entity\Team;
use App\Entity\TeamQrScan;
use App\Entity\TeamQrSequence;
use App\Repository\EscapeGameRepository;
use App\Repository\TeamRepository;
use App\Services\GameStateBroadcaster;
use App\Services\GameValidationService;
use App\Services\TeamPinService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GameController extends AbstractController
{
    private const SESSION_TEAM_CODE = 'game_team_code';
    private const SESSION_PROGRESS = 'game_progress';
    private const SESSION_CURRENT_STEP = 'game_current_step';

    #[Route('/join', name: 'game_join', methods: ['GET', 'POST'])]
    public function join(
        Request $request,
        SessionInterface $session,
        EscapeGameRepository $escapeGameRepository,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $escapeGame = $escapeGameRepository->findLatest();
        $gameOpen = $this->isGameOpen($escapeGame);
        $error = null;
        $team = null;

        if ($request->isMethod('POST')) {
            if (!$gameOpen) {
                $error = 'Les inscriptions sont actuellement fermées.';
            } else {
                $teamCode = strtoupper(trim((string) $request->request->get('team_code')));
                if ($teamCode === '') {
                    $error = 'Merci de saisir un code d\'équipe.';
                } elseif ($escapeGame === null) {
                    $error = 'Aucun jeu actif pour le moment.';
                } elseif (!$this->isTeamCodeAllowed($escapeGame, $teamCode)) {
                    $error = 'Code d\'équipe invalide.';
                } else {
                    $existingTeam = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
                    if ($existingTeam !== null) {
                        if ($existingTeam->getEscapeGame()->getId() !== $escapeGame->getId()) {
                            $error = 'Ce code ne correspond pas au jeu en cours.';
                        }
                    } else {
                        $team = $this->registerTeam($escapeGame, $teamCode, $entityManager);
                        $entityManager->flush();
                    }

                    if ($error !== null) {
                        return $this->render('game/join.html.twig', [
                            'game_open' => $gameOpen,
                            'error' => $error,
                            'team_code' => $session->get(self::SESSION_TEAM_CODE),
                            'escape_game' => $escapeGame,
                        ]);
                    }
                    $session->set(self::SESSION_TEAM_CODE, $teamCode);
                    $progress = $team !== null ? $this->buildTeamProgress($team) : $this->initializeProgress();
                    $session->set(self::SESSION_PROGRESS, $progress);
                    $session->set(self::SESSION_CURRENT_STEP, $this->resolveCurrentStep($progress));

                    return $this->redirectToRoute('game_waiting');
                }
            }
        }

        return $this->render('game/join.html.twig', [
            'game_open' => $gameOpen,
            'error' => $error,
            'team_code' => $session->get(self::SESSION_TEAM_CODE),
            'escape_game' => $escapeGame,
            'back'=> false
        ]);
    }

    #[Route('/waiting', name: 'game_waiting', methods: ['GET'])]
    public function waiting(SessionInterface $session, TeamRepository $teamRepository): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->redirectToRoute('game_join');
        }
        $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
        if ($team === null) {
            return $this->redirectToRoute('game_join');
        }

        $escapeGame = $team->getEscapeGame();
        $status = $escapeGame->getStatus();
        $options = $escapeGame->getOptions();
        $winnerName = $options['winner_team_name'] ?? null;
        $progress = $this->buildTeamProgress($team);
        $currentStep = $this->resolveCurrentStep($progress);
        $session->set(self::SESSION_PROGRESS, $progress);
        $session->set(self::SESSION_CURRENT_STEP, $currentStep);

        if ($status === 'active') {
            return $this->redirectToRoute('game_step', ['step' => $currentStep]);
        }

        return $this->render('game/waiting.html.twig', [
            'team_code' => $teamCode,
            'game_status' => $status,
            'current_step' => $currentStep,
            'escape_id' => $escapeGame->getId(),
            'winner_name' => $winnerName,
            'back'=> false
        ]);
    }

    #[Route('/game/step/{step}', name: 'game_step', requirements: ['step' => '[A-F]'], methods: ['GET', 'POST'])]
    public function step(
        string $step,
        Request $request,
        SessionInterface $session,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        GameStateBroadcaster $broadcaster,
        GameValidationService $validator
    ): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        $team = null;
        if ($teamCode) {
            $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
        }

        if ($team === null) {
            $team = $this->resolveTeamFromRequest($request, $teamRepository);
            if ($team === null) {
                return $this->redirectToRoute('game_join');
            }
            $teamCode = $team->getRegistrationCode();
            $session->set(self::SESSION_TEAM_CODE, $teamCode);
        }

        if ($team->getEscapeGame()->getStatus() !== 'active') {
            return $this->redirectToRoute('game_waiting');
        }

        $progress = $this->buildTeamProgress($team);
        $currentStep = $this->resolveCurrentStep($progress);
        $session->set(self::SESSION_PROGRESS, $progress);
        $session->set(self::SESSION_CURRENT_STEP, $currentStep);
        $error = null;

        if (!array_key_exists($step, $progress)) {
            return $this->redirectToRoute('game_step', ['step' => $currentStep]);
        }

        if ($this->isStepAfter($step, $currentStep)) {
            return $this->redirectToRoute('game_step', ['step' => $currentStep]);
        }

        if ($request->isMethod('POST') && in_array($step, ['A', 'B', 'C', 'D'], true)) {
            $letter = strtoupper(trim((string)$request->request->get('letter')));
            if ($letter === '') {
                $error = 'Merci de saisir une lettre.';
            } else {
                $stepEntity = $this->findStepForTeam($team, $step, $entityManager);
                if ($stepEntity === null) {
                    $error = 'Étape inconnue.';
                } else {
                    $result = $validator->validateStep($team, $stepEntity, ['letter' => $letter]);
                    if ($result['updated'] ?? false) {
                        $entityManager->flush();
                    }

                    if ($result['valid']) {
                        $progress = $this->buildTeamProgress($team);
                        $currentStep = $this->resolveCurrentStep($progress);
                        $session->set(self::SESSION_PROGRESS, $progress);
                        $session->set(self::SESSION_CURRENT_STEP, $currentStep);

                        $nextStep = $this->getNextStep($step);
                        if ($nextStep === null) {
                            return $this->redirectToRoute('game_final');
                        }

                        return $this->redirectToRoute('game_step', ['step' => $nextStep]);
                    }

                    $error = $result['message'] ?? 'Recommencez, ce n\'est pas la bonne réponse.';
                }
            }
        }

        $scannedQrCodes = [];
        $scannedQrDetails = [];
        $teamPayload = null;
        if ($step === 'E') {
            $qrSequences = $team->getTeamQrSequences()->toArray();
            usort($qrSequences, static fn (TeamQrSequence $left, TeamQrSequence $right): int => $left->getOrderNumber() <=> $right->getOrderNumber());
            foreach ($qrSequences as $sequence) {
                if ($sequence->isValidated()) {
                    $codeLabel = sprintf('QR%d', $sequence->getOrderNumber());
                    $scannedQrCodes[] = $codeLabel;
                    $scannedQrDetails[] = [
                        'code' => $codeLabel,
                        'message' => $sequence->getHint(),
                    ];
                }
            }

            $teamPayload = $broadcaster->buildTeamPayload($team);
        }

        if ($step === 'F') {
            return $this->redirectToRoute('game_final');
        }

        return $this->render('game/step.html.twig', [
            'team_code' => $teamCode,
            'step' => $step,
            'current_step' => $currentStep,
            'progress' => $progress,
            'error' => $error,
            'scanned_qr_codes' => $scannedQrCodes,
            'scanned_qr_details' => $scannedQrDetails,
            'team_payload' => $teamPayload,
            'escape_id' => $team->getEscapeGame()->getId(),
            'back'=> false
        ]);
    }

    #[Route('/game/status', name: 'game_status', methods: ['GET'])]
    public function status(SessionInterface $session, TeamRepository $teamRepository): JsonResponse
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->json([
                'status' => 'unknown',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
        if ($team === null) {
            return $this->json([
                'status' => 'unknown',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'status' => $team->getEscapeGame()->getStatus(),
        ]);
    }

    #[Route('/game/qr/{code}', name: 'game_qr_scan', requirements: ['code' => '[^/]+'], methods: ['GET'])]
    public function qrScan(
        string $code,
        Request $request,
        EscapeGameRepository $escapeGameRepository
    ): Response {
        $code = trim($code);
        $escapeGame = $escapeGameRepository->findLatestByStatuses(['active']);

        if ($escapeGame === null) {
            return $this->render('game/no_escape.html.twig', [
                'back' => false,
            ]);
        }

        return $this->render('game/qr_scan.html.twig', [
            'team_code' => strtoupper(trim((string) $request->query->get('team'))),
            'code' => $code,
            'result' => [
                'valid' => false,
                'message' => $code === '' ? 'Code QR manquant.' : 'Renseignez le code équipe pour valider ce QR.',
                'nextHint' => null,
                'completed' => false,
            ],
            'back'=> false
        ]);
    }

    #[Route('/game/qr/validate', name: 'game_qr_validate', methods: ['POST'])]
    public function qrValidate(
        Request $request,
        TeamRepository $teamRepository,
        EscapeGameRepository $escapeGameRepository,
        EntityManagerInterface $entityManager,
        GameValidationService $validator,
        TeamPinService $teamPinService,
        GameStateBroadcaster $broadcaster
    ): Response {
        $teamCode = strtoupper(trim((string) $request->request->get('team_code')));
        $pin = trim((string) $request->request->get('pin'));
        $code = trim((string) $request->request->get('qr_payload'));
        $expectsJson = $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept'), 'application/json');
        $payload = [
            'valid' => false,
            'message' => 'Code QR manquant.',
            'nextHint' => null,
            'completed' => false,
            'sequence' => null,
        ];

        $statusCode = JsonResponse::HTTP_UNPROCESSABLE_ENTITY;

        if ($teamCode === '') {
            $payload['message'] = 'Code équipe manquant.';
        } elseif ($pin === '') {
            $payload['message'] = 'PIN manquant.';
        } elseif ($code === '') {
            $payload['message'] = 'Code QR manquant.';
        } else {
            $team = $this->resolveTeamForQrValidation($teamCode, $teamRepository, $escapeGameRepository, $entityManager);
            if ($team === null) {
                $payload['message'] = 'Équipe introuvable.';
            } elseif (!$teamPinService->isPinValid($team, $pin)) {
                $payload['message'] = 'PIN incorrect.';
            } else {
                $step = $this->findStepForTeam($team, 'E', $entityManager);
                if ($step === null) {
                    $payload['message'] = 'Étape inconnue.';
                } else {
                    $result = $validator->validateStep($team, $step, ['code' => $code]);
                    $payload['valid'] = $result['valid'];
                    $payload['message'] = $result['message'] ?? $payload['message'];
                    $payload['nextHint'] = $result['nextHint'] ?? null;
                    $payload['completed'] = $result['completed'] ?? false;
                    $payload['sequence'] = $result['sequence'] ?? null;
                    $scanRecorded = false;
                    if (($result['valid'] ?? false) && ($result['sequence'] ?? null) instanceof TeamQrSequence) {
                        $scan = new TeamQrScan();
                        $scan->setTeam($team);
                        $scan->setQrSequence($result['sequence']);
                        $scan->setScannedByUserAgent($this->truncateUserAgent($request->headers->get('User-Agent')));
                        $entityManager->persist($scan);
                        $scanRecorded = true;
                    }

                    if (($result['updated'] ?? false) || $scanRecorded) {
                        $entityManager->flush();
                    }

                    if ($result['valid'] ?? false) {
                        $broadcaster->publishTeamProgressUpdated($team);
                    }
                }
            }
        }

        if ($payload['valid']) {
            $statusCode = JsonResponse::HTTP_OK;
        }

        if ($expectsJson) {
            $qrNumber = null;
            if ($payload['sequence'] instanceof TeamQrSequence) {
                $qrNumber = $payload['sequence']->getOrderNumber();
            }

            return $this->json([
                'ok' => $payload['valid'],
                'message' => $payload['valid'] ? $payload['message'] : null,
                'error' => $payload['valid'] ? null : $payload['message'],
                'qrNumber' => $qrNumber,
                'isFinal' => (bool) $payload['completed'],
            ], $statusCode);
        }

        if ($payload['valid']) {
            $this->addFlash('success', $payload['message']);
        } else {
            $this->addFlash('error', $payload['message']);
        }

        return $this->redirectToRoute('game_qr_scan', [
            'code' => $code,
            'team' => $teamCode,
        ]);
    }

    #[Route('/game/home', name: 'game_home', methods: ['GET'])]
    public function home(EscapeGameRepository $escapeGameRepository): Response
    {
        $escapeGame = $escapeGameRepository->findLatest();

        return $this->render('screen/home.html.twig', [
            'escape_game' => $escapeGame,
            'join_url' => $this->generateUrl('game_join', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'back'=> true
        ]);
    }

    #[Route('/game/scoreboard', name: 'game_scoreboard', methods: ['GET'])]
    public function scoreboard(EscapeGameRepository $escapeGameRepository, GameStateBroadcaster $broadcaster): Response
    {
        $escapeGame = $escapeGameRepository->findLatest();
        $scoreboard = $broadcaster->buildScoreboardPayload($escapeGame);

        return $this->render('screen/scoreboard.html.twig', [
            'escape_game' => $escapeGame,
            'scoreboard' => $scoreboard,
            'back'=> false
        ]);
    }

    #[Route('/game/scoreboard/data', name: 'game_scoreboard_data', methods: ['GET'])]
    public function scoreboardData(EscapeGameRepository $escapeGameRepository, GameStateBroadcaster $broadcaster): JsonResponse
    {
        $escapeGame = $escapeGameRepository->findLatest();

        return $this->json($broadcaster->buildScoreboardPayload($escapeGame));
    }

    #[Route('/game/scoreboard/final', name: 'game_scoreboard_final', methods: ['GET'])]
    public function scoreboardFinal(EscapeGameRepository $escapeGameRepository, GameStateBroadcaster $broadcaster): Response
    {
        $escapeGame = $escapeGameRepository->findLatest();
        $scoreboard = $broadcaster->buildScoreboardPayload($escapeGame);
        $winner = $scoreboard['winner'] ?? null;

        if ($escapeGame === null || $winner === null) {
            return $this->redirectToRoute('game_scoreboard');
        }

        return $this->render('screen/winner.html.twig', [
            'escape_game' => $escapeGame,
            'winner' => $winner,
            'scoreboard' => $scoreboard,
            'back' => false,
        ]);
    }

    #[Route('/game/team/state', name: 'game_team_state', methods: ['GET'])]
    public function teamState(
        SessionInterface $session,
        TeamRepository $teamRepository,
        GameStateBroadcaster $broadcaster
    ): JsonResponse {
        $team = $this->findTeamFromSession($session, $teamRepository);
        if ($team === null) {
            return $this->json([
                'status' => 'unknown',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json($broadcaster->buildTeamPayload($team));
    }

    #[Route('/game/scoreboard/stream', name: 'game_scoreboard_stream', methods: ['GET'])]
    public function scoreboardStream(EscapeGameRepository $escapeGameRepository, GameStateBroadcaster $broadcaster): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($escapeGameRepository, $broadcaster): void {
            ignore_user_abort(true);
            $start = time();
            $lastPayload = null;

            while (!connection_aborted() && (time() - $start) < 20) {
                $payload = $broadcaster->buildScoreboardPayload($escapeGameRepository->findLatest());
                $encoded = json_encode($payload);

                if ($encoded !== $lastPayload) {
                    echo "event: scoreboard\n";
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

    #[Route('/game/validate', name: 'game_validate', methods: ['POST'])]
    public function validate(
        Request $request,
        SessionInterface $session,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        GameValidationService $validator
    ): JsonResponse {
        $payload = $this->decodeJson($request);
        $stepType = strtoupper((string) ($payload['step'] ?? ''));

        if ($stepType === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Étape manquante.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $team = $this->findTeamFromSession($session, $teamRepository);
        if ($team === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Équipe introuvable.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $step = $this->findStepForTeam($team, $stepType, $entityManager);
        if ($step === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Étape inconnue.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $validator->validateStep($team, $step, $payload);
        if ($result['updated'] ?? false) {
            $entityManager->flush();
        }

        $response = [
            'valid' => $result['valid'],
            'message' => $result['message'],
            'step' => $stepType,
        ];

        if (array_key_exists('completed', $result)) {
            $response['completed'] = $result['completed'];
        }

        if (array_key_exists('nextHint', $result)) {
            $response['nextHint'] = $result['nextHint'];
        }

        return $this->json($response);
    }

    #[Route('/game/status/stream', name: 'game_status_stream', methods: ['GET'])]
    public function statusStream(EscapeGameRepository $escapeGameRepository): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($escapeGameRepository): void {
            ignore_user_abort(true);
            $start = time();
            $lastPayload = null;

            while (!connection_aborted() && (time() - $start) < 20) {
                $escapeGame = $escapeGameRepository->findLatest();
                $payload = [
                    'status' => $escapeGame?->getStatus() ?? 'offline',
                    'escape_name' => $escapeGame?->getName(),
                    'updated_at' => $escapeGame?->getUpdatedAt()?->format('H:i'),
                ];
                $encoded = json_encode($payload);

                if ($encoded !== $lastPayload) {
                    echo "event: escape_status\n";
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


    #[Route('/game/final', name: 'game_final', methods: ['GET'])]
    public function final(
        SessionInterface $session,
        TeamRepository $teamRepository
    ): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->redirectToRoute('game_join');
        }

        $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
        if ($team === null) {
            return $this->redirectToRoute('game_join');
        }

        if ($team->getEscapeGame()->getStatus() !== 'active') {
            return $this->redirectToRoute('game_waiting');
        }

        $progress = $this->buildTeamProgress($team);
        $currentStep = $this->resolveCurrentStep($progress);
        if ($currentStep !== 'F') {
            return $this->redirectToRoute('game_step', ['step' => $currentStep]);
        }

        $letters = $this->resolveFinalLetters($team);
        $scrambled = $letters;
        shuffle($scrambled);

        $options = $team->getEscapeGame()->getOptions();

        return $this->render('game/final.html.twig', [
            'team_code' => $teamCode,
            'letters' => $letters,
            'scrambled_letters' => $scrambled,
            'combination' => implode('', $letters),
            'cryptex_message' => $options['cryptex_message'] ?? 'Bravo !',
            'back'=> false
        ]);
    }

    #[Route('/game/winner', name: 'game_winner', methods: ['GET'])]
    public function winner(SessionInterface $session, TeamRepository $teamRepository): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->redirectToRoute('game_join');
        }

        $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
        if ($team === null) {
            return $this->redirectToRoute('game_join');
        }

        $escapeGame = $team->getEscapeGame();
        $options = $escapeGame->getOptions();
        $winnerCode = $options['winner_team_code'] ?? null;
        $progress = $this->buildTeamProgress($team);
        $hasCompletedFinal = (bool) ($progress['F']['completed'] ?? false);
        if (($winnerCode === null || $winnerCode !== $team->getRegistrationCode()) && !$hasCompletedFinal) {
            return $this->redirectToRoute('game_waiting');
        }

        return $this->render('game/winner.html.twig', [
            'team_code' => $teamCode,
            'winner_name' => $options['winner_team_name'] ?? $team->getName(),
            'message_winner' => $options['cryptex_message'] ?? 'Bravo !',
            'back' => false,
        ]);
    }

    private function isGameOpen(?EscapeGame $escapeGame): bool
    {
        if ($escapeGame === null) {
            return false;
        }

        return $escapeGame->getStatus() === 'waiting';
    }

    private function initializeProgress(): array
    {
        $progress = [];
        foreach ($this->getSteps() as $step) {
            $progress[$step] = [
                'completed' => false,
                'completedAt' => null,
            ];
        }

        return $progress;
    }

    private function getSteps(): array
    {
        return ['A', 'B', 'C', 'D', 'E', 'F'];
    }

    private function getNextStep(string $step): ?string
    {
        $steps = $this->getSteps();
        $index = array_search($step, $steps, true);

        if ($index === false) {
            return null;
        }

        return $steps[$index + 1] ?? null;
    }

    private function isStepAfter(string $candidate, string $reference): bool
    {
        $steps = $this->getSteps();
        $candidateIndex = array_search($candidate, $steps, true);
        $referenceIndex = array_search($reference, $steps, true);

        if ($candidateIndex === false || $referenceIndex === false) {
            return false;
        }

        return $candidateIndex > $referenceIndex;
    }
    private function decodeJson(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function findTeamFromSession(SessionInterface $session, TeamRepository $teamRepository): ?Team
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return null;
        }

        return $teamRepository->findOneBy(['registrationCode' => $teamCode]);
    }

    private function resolveTeamFromRequest(Request $request, TeamRepository $teamRepository): ?Team
    {
        $teamCode = strtoupper(trim((string) $request->query->get('team')));
        if ($teamCode !== '') {
            $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
            if ($team !== null) {
                return $team;
            }
        }


        $token = trim((string) $request->query->get('token'));
        if ($token !== '') {
        return $teamRepository->findOneBy(['qrToken' => $token]);
        }
        return null;
    }

    private function resolveTeamForQrValidation(
        string $teamCode,
        TeamRepository $teamRepository,
        EscapeGameRepository $escapeGameRepository,
        EntityManagerInterface $entityManager
    ): ?Team {
        $team = $teamRepository->findOneBy(['registrationCode' => $teamCode]);
        if ($team !== null) {
            return $team;
        }

        $escapeGame = $escapeGameRepository->findLatest();
        if ($escapeGame === null || !$this->isTeamCodeAllowed($escapeGame, $teamCode)) {
            return null;
        }

        $team = $this->registerTeam($escapeGame, $teamCode, $entityManager);
        $entityManager->flush();

        return $team;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return substr($userAgent, 0, 255);
    }


    private function findStepForTeam(Team $team, string $type, EntityManagerInterface $entityManager): ?Step
    {
        return $entityManager->getRepository(Step::class)->findOneBy([
            'escapeGame' => $team->getEscapeGame(),
            'type' => $type,
        ]);
    }

    private function isTeamCodeAllowed(EscapeGame $escapeGame, string $teamCode): bool
    {
        return $this->findTeamIndex($escapeGame, $teamCode) !== null;
    }

    private function findTeamIndex(EscapeGame $escapeGame, string $teamCode): ?int
    {
        $codes = $escapeGame->getOptions()['team_codes'] ?? [];
        foreach ($codes as $index => $code) {
            if (strtoupper(trim((string) $code)) === $teamCode) {
                return (int) $index;
            }
        }

        return null;
    }

    private function registerTeam(
        EscapeGame $escapeGame,
        string $teamCode,
        EntityManagerInterface $entityManager
    ): Team {
        $teamIndex = $this->findTeamIndex($escapeGame, $teamCode);
        $team = new Team();
        $team->setEscapeGame($escapeGame);
        $team->setName(sprintf('Équipe %d', $teamIndex ?? 0));
        $team->setRegistrationCode($teamCode);
        $team->setQrToken(bin2hex(random_bytes(8)));
        $team->setState('waiting');
        $team->setScore(0);
        $team->setLetterOrder([]);

        $this->configureQrSequences($team, $escapeGame, $teamIndex);
        $entityManager->persist($team);

        return $team;
    }

    private function buildTeamProgress(Team $team): array
    {
        $progress = $this->initializeProgress();

        foreach ($team->getTeamStepProgresses() as $stepProgress) {
            $stepType = strtoupper($stepProgress->getStep()?->getType() ?? '');
            if (!array_key_exists($stepType, $progress)) {
                continue;
            }

            if ($stepProgress->getState() === 'validated') {
                $progress[$stepType]['completed'] = true;
                $progress[$stepType]['completedAt'] = $stepProgress->getUpdatedAt()->format('H:i');
            }
        }

        return $progress;
    }

    private function resolveCurrentStep(array $progress): string
    {
        foreach ($this->getSteps() as $step) {
            if (!($progress[$step]['completed'] ?? false)) {
                return $step;
            }
        }

        $steps = $this->getSteps();

        return $steps[count($steps) - 1] ?? 'F';
    }



    private function configureQrSequences(Team $team, EscapeGame $escapeGame, ?int $teamIndex): void
    {
        if ($teamIndex === null) {
            return;
        }

        $sequences = $escapeGame->getOptions()['qr_sequences']['teams'][$teamIndex] ?? [];
        $orderNumber = 1;
        foreach ($sequences as $sequence) {
            if (!is_array($sequence)) {
                continue;
            }

            $qrCode = trim((string) ($sequence['code'] ?? ''));
            if ($qrCode === '') {
                continue;
            }

            $teamSequence = new TeamQrSequence();
            $teamSequence->setTeam($team);
            $teamSequence->setOrderNumber($orderNumber);
            $teamSequence->setQrCode($qrCode);
            $teamSequence->setHint($sequence['message'] ?? null);
            $teamSequence->setValidated(false);
            $team->addTeamQrSequence($teamSequence);
            $orderNumber++;
        }
    }

    /**
     * @return string[]
     */
    private function resolveFinalLetters(Team $team): array
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

        $letters = array_filter($letters, static fn (string $letter): bool => trim($letter) !== '');
        $letters = array_map(static fn (string $letter): string => strtoupper($letter), $letters);

        return array_values($letters);
    }
}