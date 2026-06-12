<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\InformationSheet;
use App\Form\InformationSheetType;
use App\Repository\InformationSheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/fiches')]
class AdminInformationSheetController extends AbstractController
{
    private const UPLOAD_DIR = '/uploads/information-sheets';

    #[Route('', name: 'admin_information_sheet_list', methods: ['GET'])]
    public function list(InformationSheetRepository $repository, Request $request): Response
    {
        $category = $request->query->getString('category');
        $query = $request->query->getString('q');

        return $this->render('admin/information_sheet/list.html.twig', [
            'sheets' => $repository->findForPublic($category !== '' ? $category : null, $query !== '' ? $query : null),
            'categories' => InformationSheet::CATEGORY_LABELS,
            'currentCategory' => $category,
            'query' => $query,
        ]);
    }

    #[Route('/new', name: 'admin_information_sheet_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, InformationSheetRepository $repository): Response
    {
        $sheet = new InformationSheet();
        $form = $this->createForm(InformationSheetType::class, $sheet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sheet->setSlug($this->createUniqueSlug($sheet->getTitle(), $slugger, $repository));
            $this->handleImageUpload($form->get('imageFile')->getData(), $sheet, $slugger);

            $entityManager->persist($sheet);
            $entityManager->flush();

            $this->addFlash('success', 'Fiche créée avec succès.');

            return $this->redirectToRoute('admin_information_sheet_list');
        }

        return $this->render('admin/information_sheet/new.html.twig', [
            'form' => $form,
            'scroll' => true,
        ]);
    }

    #[Route('/{id}', name: 'admin_information_sheet_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(InformationSheet $sheet): Response
    {
        return $this->render('admin/information_sheet/show.html.twig', [
            'sheet' => $sheet,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_information_sheet_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, InformationSheet $sheet, EntityManagerInterface $entityManager, SluggerInterface $slugger, InformationSheetRepository $repository): Response
    {
        $form = $this->createForm(InformationSheetType::class, $sheet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sheet->setSlug($this->createUniqueSlug($sheet->getTitle(), $slugger, $repository, $sheet));
            $sheet->setUpdatedAt(new \DateTimeImmutable());
            $this->handleImageUpload($form->get('imageFile')->getData(), $sheet, $slugger);

            $entityManager->flush();

            $this->addFlash('success', 'Fiche mise à jour avec succès.');

            return $this->redirectToRoute('admin_information_sheet_show', ['id' => $sheet->getId()]);
        }

        return $this->render('admin/information_sheet/edit.html.twig', [
            'sheet' => $sheet,
            'form' => $form,
            'scroll' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_information_sheet_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, InformationSheet $sheet, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_information_sheet_' . $sheet->getId(), $request->request->getString('_token'))) {
            $entityManager->remove($sheet);
            $entityManager->flush();
            $this->addFlash('success', 'Fiche supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_information_sheet_list');
    }

    private function createUniqueSlug(string $title, SluggerInterface $slugger, InformationSheetRepository $repository, ?InformationSheet $currentSheet = null): string
    {
        $baseSlug = strtolower($slugger->slug($title)->toString());
        $baseSlug = $baseSlug !== '' ? substr($baseSlug, 0, 170) : 'fiche';
        $slug = $baseSlug;
        $suffix = 2;

        while (($existing = $repository->findOneBy(['slug' => $slug])) !== null && $existing !== $currentSheet) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix++);
        }

        return $slug;
    }

    private function handleImageUpload(mixed $imageFile, InformationSheet $sheet, SluggerInterface $slugger): void
    {
        if (!$imageFile instanceof UploadedFile) {
            return;
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = strtolower($slugger->slug($originalFilename)->toString());
        $newFilename = sprintf('%s-%s.%s', $safeFilename ?: 'fiche', uniqid('', true), $imageFile->guessExtension() ?: 'bin');
        $targetDirectory = $this->getParameter('kernel.project_dir') . '/public' . self::UPLOAD_DIR;

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $imageFile->move($targetDirectory, $newFilename);
        $sheet->setImagePath(self::UPLOAD_DIR . '/' . $newFilename);
    }
}