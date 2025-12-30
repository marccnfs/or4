<?php

namespace App\Controller\Admin;

use App\Entity\EscapeGame;
use App\Form\EscapeGameType;
use App\Repository\EscapeGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
            $escapeGame->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->persist($escapeGame);
            $entityManager->flush();

            $this->addFlash('success', 'Escape créé avec succès.');

            return $this->redirectToRoute('admin_escape_list');
        }

        return $this->render('admin/escape/new.html.twig', [
            'form' => $form,
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
            $escapeGame->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Escape mis à jour avec succès.');

            return $this->redirectToRoute('admin_escape_show', ['id' => $escapeGame->getId()]);
        }

        return $this->render('admin/escape/edit.html.twig', [
            'escape' => $escapeGame,
            'form' => $form,
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
}