<?php

namespace App\Controller\Escapegame;

use App\Entity\Step;
use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Services\GameValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class GameController extends AbstractController
{
    private const SESSION_TEAM_CODE = 'game_team_code';
    private const SESSION_PROGRESS = 'game_progress';
    private const SESSION_CURRENT_STEP = 'game_current_step';

    #[Route('/join', name: 'game_join', methods: ['GET', 'POST'])]
    public function join(Request $request, SessionInterface $session): Response
    {
        $gameOpen = $this->isGameOpen();
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$gameOpen) {
                $error = 'Les inscriptions sont actuellement fermées.';
            } else {
                $teamCode = trim((string) $request->request->get('team_code'));
                if ($teamCode === '') {
                    $error = 'Merci de saisir un code d\'équipe.';
                } else {
                    $session->set(self::SESSION_TEAM_CODE, $teamCode);
                    $session->set(self::SESSION_CURRENT_STEP, 'A');
                    $session->set(self::SESSION_PROGRESS, $this->initializeProgress());

                    return $this->redirectToRoute('game_waiting');
                }
            }
        }

        return $this->render('game/join.html.twig', [
            'game_open' => $gameOpen,
            'error' => $error,
            'team_code' => $session->get(self::SESSION_TEAM_CODE),
        ]);
    }

    #[Route('/waiting', name: 'game_waiting', methods: ['GET'])]
    public function waiting(SessionInterface $session): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->redirectToRoute('game_join');
        }

        $currentStep = $session->get(self::SESSION_CURRENT_STEP, 'A');

        return $this->render('game/waiting.html.twig', [
            'team_code' => $teamCode,
            'game_open' => $this->isGameOpen(),
            'current_step' => $currentStep,
        ]);
    }

    #[Route('/game/step/{step}', name: 'game_step', requirements: ['step' => '[A-F]'], methods: ['GET', 'POST'])]
    public function step(string $step, Request $request, SessionInterface $session): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->redirectToRoute('game_join');
        }

        $progress = $session->get(self::SESSION_PROGRESS, $this->initializeProgress());
        $currentStep = $session->get(self::SESSION_CURRENT_STEP, 'A');

        if (!array_key_exists($step, $progress)) {
            return $this->redirectToRoute('game_step', ['step' => $currentStep]);
        }

        if ($this->isStepAfter($step, $currentStep)) {
            return $this->redirectToRoute('game_step', ['step' => $currentStep]);
        }

        if ($request->isMethod('POST')) {
            $progress[$step]['completed'] = true;
            $progress[$step]['completedAt'] = (new \DateTimeImmutable())->format('H:i');

            $nextStep = $this->getNextStep($step);
            if ($nextStep === null) {
                $session->set(self::SESSION_PROGRESS, $progress);
                return $this->redirectToRoute('game_final');
            }

            $session->set(self::SESSION_PROGRESS, $progress);
            $session->set(self::SESSION_CURRENT_STEP, $nextStep);

            return $this->redirectToRoute('game_step', ['step' => $nextStep]);
        }

        return $this->render('game/step.html.twig', [
            'team_code' => $teamCode,
            'step' => $step,
            'current_step' => $currentStep,
            'progress' => $progress,
            'game_open' => $this->isGameOpen(),
        ]);
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


    #[Route('/game/final', name: 'game_final', methods: ['GET'])]
    public function final(SessionInterface $session): Response
    {
        $teamCode = $session->get(self::SESSION_TEAM_CODE);
        if (!$teamCode) {
            return $this->redirectToRoute('game_join');
        }

        $progress = $session->get(self::SESSION_PROGRESS, $this->initializeProgress());
        $completedCount = count(array_filter($progress, static fn (array $data): bool => $data['completed']));

        return $this->render('game/final.html.twig', [
            'team_code' => $teamCode,
            'completed_count' => $completedCount,
            'total_steps' => count($progress),
        ]);
    }

    private function isGameOpen(): bool
    {
        $flag = getenv('GAME_OPEN');
        if ($flag === false) {
            return true;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
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

    private function findStepForTeam(Team $team, string $type, EntityManagerInterface $entityManager): ?Step
    {
        return $entityManager->getRepository(Step::class)->findOneBy([
            'escapeGame' => $team->getEscapeGame(),
            'type' => $type,
        ]);
    }
}