<?php

namespace App\Controller\Escapegame;

use App\Entity\EscapeGame;
use App\Repository\EscapeGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GamePilotController extends AbstractController
{
    #[Route('/game/pilot', name: 'game_pilot', methods: ['GET'])]
    public function index(EscapeGameRepository $escapeGameRepository): Response
    {
        $escapeGame = $escapeGameRepository->findLatest();

        return $this->render('game/pilot.html.twig', [
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

        $escapeGame->setStatus('offline');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Le jeu est arrêté.');

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

        foreach ($escapeGame->getTeams() as $team) {
            $entityManager->remove($team);
        }

        $escapeGame->setStatus('waiting');
        $escapeGame->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Les équipes ont été réinitialisées.');

        return $this->redirectToRoute('game_pilot');
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