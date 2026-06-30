<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ManualPageRepository;
use App\Repository\ManualReferenceTableRepository;
use App\Repository\ManualSectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/manuel')]
class AdminManualController extends AbstractController
{
    #[Route('', name: 'admin_manual_index', methods: ['GET'])]
    public function index(ManualSectionRepository $sections, ManualPageRepository $pages, ManualReferenceTableRepository $referenceTables): Response
    {
        return $this->render('admin/manual/index.html.twig', [
            'sectionsCount' => $sections->count([]),
            'pagesCount' => $pages->count([]),
            'draftCount' => $pages->count(['status' => 'draft']),
            'publishedCount' => $pages->count(['status' => 'published']),
            'archivedCount' => $pages->count(['status' => 'archived']),
            'referenceTablesCount' => $referenceTables->count([]),
        ]);
    }
}
