<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ManualPage;
use App\Entity\ManualPageVersion;
use App\Entity\User;
use App\Form\ManualPageType;
use App\Repository\ManualPageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/manuel/pages')]
class AdminManualPageController extends AbstractController
{
    #[Route('', name: 'admin_manual_page_list', methods: ['GET'])]
    public function list(ManualPageRepository $repository): Response
    {
        return $this->render('admin/manual/page/list.html.twig', [
            'pages' => $repository->findBy([], ['position' => 'ASC', 'title' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_manual_page_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $page = new ManualPage();
        $form = $this->createForm(ManualPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->preparePage($page, $form->get('tagsText')->getData(), $slugger, true);
            $user = $this->getUser();
            $page->setCreatedBy($user instanceof User ? $user : null);
            $page->setUpdatedBy($user instanceof User ? $user : null);
            $version = $this->createVersion($page, 1, 'Version initiale');
            $version->setCreatedBy($user instanceof User ? $user : null);
            $entityManager->persist($page);
            $entityManager->persist($version);
            $entityManager->flush();
            $this->addFlash('success', 'Page créée avec une première version.');

            return $this->redirectToRoute('admin_manual_page_show', ['id' => $page->getId()]);
        }

        return $this->render('admin/manual/page/form.html.twig', ['form' => $form, 'page' => $page, 'isEdit' => false]);
    }

    #[Route('/{id}', name: 'admin_manual_page_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(ManualPage $page): Response
    {
        return $this->render('admin/manual/page/show.html.twig', ['page' => $page]);
    }

    #[Route('/{id}/modifier', name: 'admin_manual_page_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ManualPage $page, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $originalContent = $page->getContentMarkdown();
        $form = $this->createForm(ManualPageType::class, $page);
        $form->get('tagsText')->setData(implode(', ', $page->getTags() ?? []));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contentChanged = $originalContent !== $page->getContentMarkdown();
            $this->preparePage($page, $form->get('tagsText')->getData(), $slugger, false);
            $user = $this->getUser();
            $page->setUpdatedBy($user instanceof User ? $user : null);
            $page->setUpdatedAt(new \DateTimeImmutable());

            if ($contentChanged) {
                $version = $this->createVersion($page, $page->getVersions()->count() + 1, 'Modification du contenu');
                $version->setCreatedBy($user instanceof User ? $user : null);
                $entityManager->persist($version);
            }

            $entityManager->flush();
            $this->addFlash('success', $contentChanged ? 'Page modifiée et nouvelle version créée.' : 'Page modifiée.');

            return $this->redirectToRoute('admin_manual_page_show', ['id' => $page->getId()]);
        }

        return $this->render('admin/manual/page/form.html.twig', ['form' => $form, 'page' => $page, 'isEdit' => true]);
    }

    private function preparePage(ManualPage $page, mixed $tagsText, SluggerInterface $slugger, bool $isNew): void
    {
        $slugSource = $page->getSlug() !== '' ? $page->getSlug() : $page->getTitle();
        $page->setSlug($slugger->slug($slugSource)->lower()->toString());
        $tags = array_filter(array_map('trim', explode(',', (string) $tagsText)));
        $page->setTags($tags !== [] ? $tags : null);

        if ($page->getStatus() === ManualPage::STATUS_PUBLISHED && ($isNew || $page->getPublishedAt() === null)) {
            $page->setPublishedAt(new \DateTimeImmutable());
        }
    }

    private function createVersion(ManualPage $page, int $versionNumber, string $summary): ManualPageVersion
    {
        return (new ManualPageVersion())
            ->setPage($page)
            ->setTitleSnapshot($page->getTitle())
            ->setContentMarkdownSnapshot($page->getContentMarkdown())
            ->setVersionNumber($versionNumber)
            ->setChangeSummary($summary);
    }
}
