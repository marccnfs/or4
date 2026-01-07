<?php

namespace App\Controller\Escapegame;

use App\Entity\Step;
use App\Entity\Team;
use App\Repository\EscapeGameRepository;
use App\Repository\TeamRepository;
use App\Services\GameStateBroadcaster;
use App\Services\GameValidationService;
use App\Services\TeamPinService;
use App\Entity\TeamQrScan;
use App\Entity\TeamQrSequence;
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
        TeamRepository $teamRepository,
        EscapeGameRepository $escapeGameRepository,
        EntityManagerInterface $entityManager,
        GameValidationService $validator,
        TeamPinService $teamPinService,
        GameStateBroadcaster $broadcaster
    ): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $teamCode = strtoupper(trim((string) ($payload['team_code'] ?? '')));
        $pin = trim((string) ($payload['pin'] ?? ''));
        $code = trim((string) ($payload['qr_payload'] ?? $payload['code'] ?? ''));

        if ($teamCode === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Code équipe manquant.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($pin === '') {
            return $this->json([
                'valid' => false,
                'message' => 'PIN manquant.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($code === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Code QR manquant.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $team = $this->resolveTeamFromCode($teamCode, $teamRepository, $escapeGameRepository, $entityManager);
        if ($team === null) {
            return $this->json([
                'valid' => false,
                'message' => 'Équipe introuvable.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$teamPinService->isPinValid($team, $pin)) {
            return $this->json([
                'valid' => false,
                'message' => 'PIN incorrect.',
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

        $escapeGame = $team->getEscapeGame();
        $options = $escapeGame->getOptions();
        $winnerCode = $options['winner_team_code'] ?? null;
        if ($escapeGame->getStatus() === 'finished' && $winnerCode !== $team->getRegistrationCode()) {
            $winnerName = $options['winner_team_name'] ?? 'Une autre équipe';
            return $this->json([
                'valid' => false,
                'message' => sprintf("L'équipe %s a trouvé le secret.", $winnerName),
                'finished' => true,
            ]);
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
            if ($result['valid'] && $escapeGame->getStatus() !== 'finished' && $winnerCode === null) {
                $options['winner_team_id'] = $team->getId();
                $options['winner_team_name'] = $team->getName();
                $options['winner_team_code'] = $team->getRegistrationCode();
                $escapeGame->setOptions($options);
                $escapeGame->setStatus('finished');
                $escapeGame->setUpdatedAt(new \DateTimeImmutable());
            }
            $entityManager->flush();
        }

        return $this->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
            'finished' => $escapeGame->getStatus() === 'finished',
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

    private function resolveTeamFromCode(
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
        if ($escapeGame === null) {
            return null;
        }

        $allowedCodes = $escapeGame->getOptions()['team_codes'] ?? [];
        foreach ($allowedCodes as $index => $code) {
            if (strtoupper(trim((string) $code)) === $teamCode) {
                $team = new Team();
                $team->setEscapeGame($escapeGame);
                $team->setName(sprintf('Équipe %d', (int) $index));
                $team->setRegistrationCode($teamCode);
                $team->setQrToken(bin2hex(random_bytes(8)));
                $team->setState('waiting');
                $team->setScore(0);
                $team->setLetterOrder([]);
                $entityManager->persist($team);
                $entityManager->flush();
                return $team;
            }
        }

        return null;
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
}