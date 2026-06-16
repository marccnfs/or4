<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\InformationSheet;
use App\Entity\InformationSheetRead;
use App\Entity\User;
use App\Repository\InformationSheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiches')]
class InformationSheetPublicController extends AbstractController
{
    #[Route('', name: 'information_sheet_index', methods: ['GET'])]
    public function index(Request $request, InformationSheetRepository $repository): Response
    {
        $category = $request->query->getString('category');
        $query = $request->query->getString('q');

        return $this->render($this->template($request, 'public/information_sheet/index'), [
            'sheets' => $repository->findForPublic($category !== '' ? $category : null, $query !== '' ? $query : null),
            'categories' => InformationSheet::CATEGORY_LABELS,
            'counts' => $repository->countByCategory(),
            'currentCategory' => $category,
            'query' => $query,
        ]);
    }

    #[Route('/{slug}', name: 'information_sheet_show', requirements: ['slug' => '(?!espace$)[a-z0-9-]+'], methods: ['GET'])]
    public function show(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] InformationSheet $sheet, InformationSheetRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $entityManager->persist((new InformationSheetRead())->setAgent($user)->setSheet($sheet));
            $entityManager->flush();
        }
        return $this->render($this->template($request, 'public/information_sheet/show'), [
            'sheet' => $sheet,
            'categories' => InformationSheet::CATEGORY_LABELS,
            'relatedSheets' => array_filter(
                $repository->findForPublic($sheet->getCategory()),
                static fn (InformationSheet $relatedSheet): bool => $relatedSheet->getId() !== $sheet->getId()
            ),
        ]);
    }

    private function template(Request $request, string $baseTemplate): string
    {
        $userAgent = strtolower($request->headers->get('User-Agent', ''));
        $isMobile = str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') || str_contains($userAgent, 'iphone');

        return sprintf('%s%s.html.twig', $baseTemplate, $isMobile ? '_mobile' : '');
    }
}
