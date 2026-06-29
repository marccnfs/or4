<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ManualSection;
use App\Form\ManualSectionType;
use App\Repository\ManualSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/manuel/rubriques')]
class AdminManualSectionController extends AbstractController
{
    #[Route('', name: 'admin_manual_section_list', methods: ['GET'])]
    public function list(ManualSectionRepository $repository): Response
    {
        return $this->render('admin/manual/section/list.html.twig', [
            'sections' => $repository->findBy([], ['position' => 'ASC', 'title' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_manual_section_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $section = new ManualSection();
        $form = $this->createForm(ManualSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prepareSection($section, $slugger);
            $entityManager->persist($section);
            $entityManager->flush();
            $this->addFlash('success', 'Rubrique créée.');

            return $this->redirectToRoute('admin_manual_section_list');
        }

        return $this->render('admin/manual/section/form.html.twig', ['form' => $form, 'section' => $section, 'isEdit' => false]);
    }

    #[Route('/{id}/modifier', name: 'admin_manual_section_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ManualSection $section, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ManualSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->prepareSection($section, $slugger);
            $section->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Rubrique modifiée.');

            return $this->redirectToRoute('admin_manual_section_list');
        }

        return $this->render('admin/manual/section/form.html.twig', ['form' => $form, 'section' => $section, 'isEdit' => true]);
    }

    private function prepareSection(ManualSection $section, SluggerInterface $slugger): void
    {
        $slugSource = $section->getSlug() !== '' ? $section->getSlug() : $section->getTitle();
        $section->setSlug($slugger->slug($slugSource)->lower()->toString());
    }
}
