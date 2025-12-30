<?php

namespace App\Controller\Escapegame;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiGameController extends AbstractController
{
    private const STEP_LETTERS = [
        'A' => 'A',
        'B' => 'B',
        'C' => 'C',
        'D' => 'D',
    ];

    private const QR_SEQUENCE = [
        'QR1',
        'QR2',
        'QR3',
        'QR4',
        'QR5',
    ];

    private const QR_HINTS = [
        1 => 'Indice pour le QR #2.',
        2 => 'Indice pour le QR #3.',
        3 => 'Indice pour le QR #4.',
        4 => 'Indice pour le QR #5.',
    ];

    private const FINAL_COMBINATION = 'ABCD';

    #[Route('/api/step/validate', name: 'api_step_validate', methods: ['POST'])]
    public function validateStep(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $step = strtoupper((string) ($payload['step'] ?? ''));
        $letter = strtoupper(trim((string) ($payload['letter'] ?? '')));

        if (!array_key_exists($step, self::STEP_LETTERS)) {
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

        $expected = strtoupper(self::STEP_LETTERS[$step]);
        $valid = $letter === $expected;

        return $this->json([
            'valid' => $valid,
            'step' => $step,
            'message' => $valid ? 'Bonne lettre.' : 'Lettre incorrecte.',
        ]);
    }

    #[Route('/api/qr/scan', name: 'api_qr_scan', methods: ['POST'])]
    public function scanQr(Request $request, SessionInterface $session): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $code = trim((string) ($payload['code'] ?? ''));

        if ($code === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Code QR manquant.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $sequenceIndex = (int) $session->get('qr_index', 0);
        $expected = self::QR_SEQUENCE[$sequenceIndex] ?? null;

        if ($expected === null || $code !== $expected) {
            return $this->json([
                'valid' => false,
                'message' => 'QR incorrect.',
                'nextHint' => $this->getQrHint($sequenceIndex),
                'completed' => false,
            ]);
        }

        $sequenceIndex++;
        $session->set('qr_index', $sequenceIndex);

        $completed = $sequenceIndex >= count(self::QR_SEQUENCE);

        return $this->json([
            'valid' => true,
            'message' => $completed ? 'Séquence terminée.' : 'QR validé.',
            'nextHint' => $completed ? null : $this->getQrHint($sequenceIndex),
            'completed' => $completed,
        ]);
    }

    #[Route('/api/final/check', name: 'api_final_check', methods: ['POST'])]
    public function checkFinal(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $combination = strtoupper(trim((string) ($payload['combination'] ?? '')));

        if ($combination === '') {
            return $this->json([
                'valid' => false,
                'message' => 'Combinaison manquante.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $valid = $combination === strtoupper(self::FINAL_COMBINATION);

        return $this->json([
            'valid' => $valid,
            'message' => $valid ? 'Bonne combinaison.' : 'Combinaison incorrecte.',
        ]);
    }

    private function decodeJson(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function getQrHint(int $sequenceIndex): ?string
    {
        if (!isset(self::QR_SEQUENCE[$sequenceIndex])) {
            return null;
        }

        return self::QR_HINTS[$sequenceIndex] ?? null;
    }
}