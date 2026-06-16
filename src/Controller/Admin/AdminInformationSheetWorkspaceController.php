<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\InformationSheetWorkspaceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/dossiers-fiches')]
class AdminInformationSheetWorkspaceController extends AbstractController
{
    #[Route('', name: 'admin_information_sheet_workspace_list', methods: ['GET'])]
    public function list(InformationSheetWorkspaceRepository $repository): Response
    {
        return $this->render('admin/information_sheet_workspace/list.html.twig', [
            'workspaces' => $repository->findBy([], ['updatedAt' => 'DESC', 'createdAt' => 'DESC']),
        ]);
    }
}
