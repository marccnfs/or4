<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\ManualPage;
use App\Entity\ManualSection;
use App\Repository\ManualPageRepository;
use App\Repository\ManualSectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manuel-si')]
class ManualPublicController extends AbstractController
{
    #[Route('', name: 'manual_public_index', methods: ['GET'])]
    public function index(ManualSectionRepository $sectionRepository): Response
    {
        return $this->render('public/manual/index.html.twig', [
            'sections' => $this->findPublishedSections($sectionRepository),
        ]);
    }

    #[Route('/recherche', name: 'manual_public_search', methods: ['GET'])]
    public function search(Request $request, ManualPageRepository $pageRepository, ManualSectionRepository $sectionRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        return $this->render('public/manual/search.html.twig', [
            'query' => $query,
            'pages' => $query === '' ? [] : $this->searchPublishedPages($pageRepository, $query),
            'sections' => $this->findPublishedSections($sectionRepository),
        ]);
    }

    #[Route('/{sectionSlug}', name: 'manual_public_section', requirements: ['sectionSlug' => '(?!recherche$)[a-z0-9\-]+'], methods: ['GET'])]
    public function section(string $sectionSlug, ManualSectionRepository $sectionRepository, ManualPageRepository $pageRepository): Response
    {
        $section = $this->findPublishedSectionBySlug($sectionRepository, $sectionSlug);
        if (!$section instanceof ManualSection) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }

        return $this->render('public/manual/section.html.twig', [
            'section' => $section,
            'pages' => $this->findPublishedPagesForSection($pageRepository, $section),
            'sections' => $this->findPublishedSections($sectionRepository),
        ]);
    }

    #[Route('/{sectionSlug}/{pageSlug}', name: 'manual_public_page', requirements: ['sectionSlug' => '[a-z0-9\-]+', 'pageSlug' => '[a-z0-9\-]+'], methods: ['GET'])]
    public function page(string $sectionSlug, string $pageSlug, ManualSectionRepository $sectionRepository, ManualPageRepository $pageRepository): Response
    {
        $section = $this->findPublishedSectionBySlug($sectionRepository, $sectionSlug);
        if (!$section instanceof ManualSection) {
            throw $this->createNotFoundException('Rubrique introuvable.');
        }

        $page = $this->findPublishedPageBySlug($pageRepository, $section, $pageSlug);
        if (!$page instanceof ManualPage) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        return $this->render('public/manual/page.html.twig', [
            'section' => $section,
            'page' => $page,
            'pages' => $this->findPublishedPagesForSection($pageRepository, $section),
            'sections' => $this->findPublishedSections($sectionRepository),
        ]);
    }

    /** @return ManualSection[] */
    private function findPublishedSections(ManualSectionRepository $repository): array
    {
        return $repository->createQueryBuilder('section')
            ->andWhere('section.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('section.position', 'ASC')
            ->addOrderBy('section.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function findPublishedSectionBySlug(ManualSectionRepository $repository, string $slug): ?ManualSection
    {
        return $repository->createQueryBuilder('section')
            ->andWhere('section.slug = :slug')
            ->andWhere('section.isPublished = :published')
            ->setParameter('slug', $slug)
            ->setParameter('published', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ManualPage[] */
    private function findPublishedPagesForSection(ManualPageRepository $repository, ManualSection $section): array
    {
        return $repository->createQueryBuilder('page')
            ->andWhere('page.section = :section')
            ->andWhere('page.status = :status')
            ->setParameter('section', $section)
            ->setParameter('status', ManualPage::STATUS_PUBLISHED)
            ->orderBy('page.position', 'ASC')
            ->addOrderBy('page.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function findPublishedPageBySlug(ManualPageRepository $repository, ManualSection $section, string $slug): ?ManualPage
    {
        return $repository->createQueryBuilder('page')
            ->andWhere('page.section = :section')
            ->andWhere('page.slug = :slug')
            ->andWhere('page.status = :status')
            ->setParameter('section', $section)
            ->setParameter('slug', $slug)
            ->setParameter('status', ManualPage::STATUS_PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ManualPage[] */
    private function searchPublishedPages(ManualPageRepository $repository, string $query): array
    {
        $qb = $repository->createQueryBuilder('page')
            ->addSelect('section')
            ->join('page.section', 'section')
            ->andWhere('section.isPublished = :published')
            ->andWhere('page.status = :status')
            ->setParameter('published', true)
            ->setParameter('status', ManualPage::STATUS_PUBLISHED)
            ->orderBy('section.position', 'ASC')
            ->addOrderBy('page.position', 'ASC')
            ->addOrderBy('page.title', 'ASC');

        foreach ($this->tokenizeQuery($query) as $index => $token) {
            $parameter = sprintf('search_%d', $index);
            $qb->andWhere(sprintf('(LOWER(page.title) LIKE :%1$s OR LOWER(page.summary) LIKE :%1$s OR LOWER(page.contentMarkdown) LIKE :%1$s OR page.tags LIKE :%1$s)', $parameter))
                ->setParameter($parameter, '%' . str_replace(['%', '_'], ['\\%', '\\_'], $token) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return string[] */
    private function tokenizeQuery(string $query): array
    {
        $tokens = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_slice(array_unique($tokens), 0, 8));
    }
}
