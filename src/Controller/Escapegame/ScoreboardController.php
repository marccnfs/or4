<?php

namespace App\Controller\Escapegame;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ScoreboardController extends AbstractController
{
    #[Route('/scoreboard', name: 'scoreboard_index')]
    public function index(): Response
    {
        return $this->render('scoreboard/index.html.twig');
    }

    #[Route('/scoreboard/data', name: 'scoreboard_data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        return new JsonResponse([
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'teams' => [
                [
                    'name' => 'Team Alpha',
                    'score' => 42,
                    'status' => 'active',
                ],
                [
                    'name' => 'Team Beta',
                    'score' => 35,
                    'status' => 'waiting',
                ],
                [
                    'name' => 'Team Gamma',
                    'score' => 27,
                    'status' => 'offline',
                ],
            ],
        ]);
    }
}