<?php

namespace App\Controller\Admin;

use App\Entity\EscapeGame;
use App\Entity\Step;
use App\Form\EscapeGameType;
use App\Repository\EscapeGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/escape')]
class AdminEscapeController extends AbstractController
{
    #[Route('/new', name: 'admin_escape_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $escapeGame = new EscapeGame();
        $escapeGame->setStatus('offline');
        $form = $this->createForm(EscapeGameType::class, $escapeGame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyConfiguration($escapeGame, $form);
            $escapeGame->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->persist($escapeGame);
            $entityManager->flush();

            $this->addFlash('success', 'Escape créé avec succès.');

            return $this->redirectToRoute('admin_escape_list');
        }

        return $this->render('admin/escape/new.html.twig', [
            'form' => $form,
            'scroll'=> true,
        ]);
    }

    #[Route('/list', name: 'admin_escape_list')]
    public function list(EscapeGameRepository $escapeGameRepository): Response
    {
        return $this->render('admin/escape/list.html.twig', [
            'escapes' => $escapeGameRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'admin_escape_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(EscapeGame $escapeGame): Response
    {
        return $this->render('admin/escape/show.html.twig', [
            'escape' => $escapeGame,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_escape_edit', requirements: ['id' => '\\d+'])]
    public function edit(Request $request, EscapeGame $escapeGame, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EscapeGameType::class, $escapeGame);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyConfiguration($escapeGame, $form);
            $escapeGame->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Escape mis à jour avec succès.');

            return $this->redirectToRoute('admin_escape_show', ['id' => $escapeGame->getId()]);
        }

        return $this->render('admin/escape/edit.html.twig', [
            'escape' => $escapeGame,
            'form' => $form,
            'scroll'=> true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_escape_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, EscapeGame $escapeGame, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_escape_' . $escapeGame->getId(), $request->request->get('_token'))) {
            $entityManager->remove($escapeGame);
            $entityManager->flush();
            $this->addFlash('success', 'Escape supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_escape_list');
    }

    private function applyConfiguration(EscapeGame $escapeGame, FormInterface $form): void
    {
        $teamCodes = [];
        for ($team = 1; $team <= 8; $team++) {
            $teamCodes[$team] = strtoupper(trim((string) $form->get(sprintf('team%dCode', $team))->getData()));
        }

        $stepLetters = [
            'A' => $this->sanitizeLetter($form->get('step1Letter')->getData()),
            'B' => $this->sanitizeLetter($form->get('step2Letter')->getData()),
            'C' => $this->sanitizeLetter($form->get('step3Letter')->getData()),
            'D' => $this->sanitizeLetter($form->get('step4Letter')->getData()),
            'E' => $this->sanitizeLetter($form->get('step5Letter')->getData()),
        ];

        $options = $escapeGame->getOptions();
        $options['team_codes'] = $teamCodes;
        $options['steps'] = [
            'A' => [
                'solution' => $stepLetters['A'],
                'letter' => $stepLetters['A'],
            ],
            'B' => [
                'solution' => $stepLetters['B'],
                'letter' => $stepLetters['B'],
            ],
            'C' => [
                'solution' => $stepLetters['C'],
                'letter' => $stepLetters['C'],
            ],
            'D' => [
                'solution' => $stepLetters['D'],
                'letter' => $stepLetters['D'],
            ],
            'E' => [
                'letter' => $stepLetters['E'],
            ],
        ];

        $options['cryptex_message'] = (string) $form->get('cryptexMessage')->getData();
        $options['qr_sequences'] = [
            'teams' => [],
        ];

        for ($team = 1; $team <= 8; $team++) {
            $teamSequences = [];
            for ($index = 1; $index <= 5; $index++) {
                $code = trim((string) $form->get(sprintf('team%d_qr%d_code', $team, $index))->getData());
                $message = null;
                if ($index < 5) {
                    $message = $form->get(sprintf('team%d_qr%d_message', $team, $index))->getData();
                }
                $teamSequences[] = [
                    'code' => $code,
                    'message' => $message,
                ];
            }
            $options['qr_sequences']['teams'][$team] = $teamSequences;
        }

        $escapeGame->setOptions($options);
        $this->syncSteps($escapeGame, $stepLetters);
    }

    private function syncSteps(EscapeGame $escapeGame, array $stepLetters): void
    {
        $existingSteps = [];
        foreach ($escapeGame->getSteps() as $step) {
            $existingSteps[$step->getType()] = $step;
        }

        $order = [
            'A' => 1,
            'B' => 2,
            'C' => 3,
            'D' => 4,
            'E' => 5,
            'F' => 6,
        ];

        foreach ($order as $type => $orderNumber) {
            $step = $existingSteps[$type] ?? new Step();
            $step->setType($type);
            $step->setOrderNumber($orderNumber);

            if (in_array($type, ['A', 'B', 'C', 'D'], true)) {
                $step->setSolution($stepLetters[$type]);
                $step->setLetter($stepLetters[$type]);
            } elseif ($type === 'E') {
                $step->setSolution('QR');
                $step->setLetter($stepLetters['E']);
            } else {
                $step->setSolution('CR');
                $step->setLetter('F');
            }

            if (!$escapeGame->getSteps()->contains($step)) {
                $escapeGame->addStep($step);
            }
        }
    }

    private function sanitizeLetter(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

}