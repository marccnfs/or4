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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiGameController extends AbstractController
{

    #[Route('/api/step/validate', name: 'api_step_validate', methods: ['POST'])]
    public function validateStep(
        Request $request,
        SessionInterface $session,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        GameValidationService $validator
    ): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $step = strtoupper((string) ($payload['step'] ?? ''));
        $letter = strtoupper(trim((string) ($payload['letter'] ?? '')));

        if (!in_array($step, ['A', 'B', 'C', 'D'], true)) {
            return $this->json([
                'valid' => false,
                'message' => 'Étape inconnue.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($letter === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Lettre manquante.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $team = $this->findTeamFromSession($session, $teamRepository);
        if ($team === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Équipe introuvable.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $stepEntity = $this->findStepForTeam($team, $step, $entityManager);
        if ($stepEntity === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Étape inconnue.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $validator->validateStep($team, $stepEntity, $payload);
        if ($result['updated'] ?? false) {
            $entityManager->flush();
        }

        return $this->json([
            'valid' => $result['valid'],
            'step' => $step,
            'message' => $result['message'],
        ]);
    }

    #[Route('/api/qr/scan', name: 'api_qr_scan', methods: ['POST'])]
    public function scanQr(
        Request $request,
        SessionInterface $session,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        GameValidationService $validator
    ): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $code = trim((string) ($payload['code'] ?? ''));

        if ($code === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Code QR manquant.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $team = $this->findTeamFromSession($session, $teamRepository);
        if ($team === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Équipe introuvable.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $stepEntity = $this->findStepForTeam($team, 'E', $entityManager);
        if ($stepEntity === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Étape inconnue.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $validator->validateStep($team, $stepEntity, ['code' => $code]);
        if ($result['updated'] ?? false) {
            $entityManager->flush();
        }

        return $this->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
            'nextHint' => $result['nextHint'] ?? null,
            'completed' => $result['completed'] ?? false,
        ]);
    }

    #[Route('/api/final/check', name: 'api_final_check', methods: ['POST'])]
    public function checkFinal(
        Request $request,
        SessionInterface $session,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        GameValidationService $validator
    ): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $combination = strtoupper(trim((string) ($payload['combination'] ?? '')));

        if ($combination === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Combinaison manquante.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $team = $this->findTeamFromSession($session, $teamRepository);
        if ($team === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Équipe introuvable.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $stepEntity = $this->findStepForTeam($team, 'F', $entityManager);
        if ($stepEntity === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Étape inconnue.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $validator->validateStep($team, $stepEntity, ['combination' => $combination]);
        if ($result['updated'] ?? false) {
            $entityManager->flush();
        }

        return $this->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
        ]);
    }

    private function decodeJson(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function findTeamFromSession(SessionInterface $session, TeamRepository $teamRepository): ?Team
    {
        $teamCode = $session->get('game_team_code');
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