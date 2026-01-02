<?php

namespace App\Controller\Admin;

use App\Entity\EscapeGame;
use App\Services\QrCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/escape')]
class AdminEscapeQrController extends AbstractController
{
    #[Route('/{id}/qr/{team}/preview', name: 'admin_escape_qr_preview', requirements: ['id' => '\\d+', 'team' => '\\d+'], methods: ['GET'])]
    public function preview(
        EscapeGame $escapeGame,
        int $team,
        QrCodeService $qrCodeService,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $payload = $this->buildQrPayload($escapeGame, $team, $qrCodeService, $urlGenerator);
        if ($payload === null) {
            throw $this->createNotFoundException('Équipe introuvable.');
        }

        return $this->render('admin/escape/qr_preview.html.twig', $payload);
    }

    #[Route('/{id}/qr/{team}/print', name: 'admin_escape_qr_print', requirements: ['id' => '\\d+', 'team' => '\\d+'], methods: ['GET'])]
    public function print(
        EscapeGame $escapeGame,
        int $team,
        QrCodeService $qrCodeService,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $payload = $this->buildQrPayload($escapeGame, $team, $qrCodeService, $urlGenerator);
        if ($payload === null) {
            throw $this->createNotFoundException('Équipe introuvable.');
        }

        return $this->render('admin/escape/qr_print.html.twig', $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildQrPayload(
        EscapeGame $escapeGame,
        int $team,
        QrCodeService $qrCodeService,
        UrlGeneratorInterface $urlGenerator
    ): ?array {
        if ($team < 1 || $team > 8) {
            return null;
        }

        $options = $escapeGame->getOptions();
        $teamCodes = $options['team_codes'] ?? [];
        $teamCode = $teamCodes[$team] ?? sprintf('Équipe %d', $team);
        if (trim((string) $teamCode) === '') {
            $teamCode = sprintf('Équipe %d', $team);
        }
        $sequences = $options['qr_sequences']['teams'][$team] ?? [];

        $items = [];
        for ($index = 1; $index <= 5; $index++) {
            $sequence = $sequences[$index - 1] ?? [];
            $code = trim((string) ($sequence['code'] ?? ''));
            $url = null;
            $image = null;

            if ($code !== '') {
                $url = $urlGenerator->generate('game_qr_scan', [
                    'code' => $code,
                    'team' => $teamCode,
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $image = $qrCodeService->getQrImageUrl($url);
            }

            $items[] = [
                'index' => $index,
                'code' => $code,
                'url' => $url,
                'image' => $image,
            ];
        }

        return [
            'escape' => $escapeGame,
            'team_number' => $team,
            'team_code' => $teamCode,
            'qr_items' => $items,
        ];
    }
}