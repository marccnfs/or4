<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\InformationSheet;
use App\Entity\InformationSheetWorkspace;
use App\Entity\InformationSheetWorkspaceAttachment;
use App\Entity\InformationSheetWorkspaceMessage;
use App\Entity\User;
use App\Form\InformationSheetWorkspaceType;
use App\Form\WorkspaceMessageType;
use App\Repository\InformationSheetReadRepository;
use App\Repository\InformationSheetWorkspaceAttachmentRepository;
use App\Repository\InformationSheetWorkspaceMessageRepository;
use App\Repository\InformationSheetWorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_AGENT')]
#[Route('/fiches/espace')]
class InformationSheetWorkspaceController extends AbstractController
{
    private const UPLOAD_DIR = '/uploads/information-sheet-workspaces';

    #[Route('', name: 'information_sheet_workspace_index', methods: ['GET'])]
    public function index(InformationSheetWorkspaceRepository $workspaceRepository, InformationSheetReadRepository $readRepository): Response
    {
        $agent = $this->getAgentUser();

        return $this->render('public/information_sheet/workspace/index.html.twig', [
            'workspaces' => $workspaceRepository->findForAgent($agent),
            'reads' => $readRepository->findRecentForAgent($agent),
        ]);
    }

    #[Route('/creer/{slug}', name: 'information_sheet_workspace_create', methods: ['POST'])]
    public function create(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] InformationSheet $sheet, EntityManagerInterface $entityManager, InformationSheetWorkspaceRepository $workspaceRepository): Response
    {
        if (!$this->isCsrfTokenValid('create_workspace_' . $sheet->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $agent = $this->getAgentUser();
        $workspace = $workspaceRepository->findOneForAgentAndSheet($agent, $sheet);

        if ($workspace === null) {
            $workspace = InformationSheetWorkspace::fromSheet($agent, $sheet);
            $entityManager->persist($workspace);
            $entityManager->flush();
            $this->addFlash('success', 'La fiche est maintenant votre dossier de travail personnel.');
        }

        return $this->redirectToRoute('information_sheet_workspace_show', ['id' => $workspace->getId()]);
    }

    #[Route('/dossiers/{id}', name: 'information_sheet_workspace_show', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, InformationSheetWorkspace $workspace, EntityManagerInterface $entityManager, SluggerInterface $slugger, InformationSheetWorkspaceAttachmentRepository $attachmentRepository, InformationSheetWorkspaceMessageRepository $messageRepository): Response
    {
        $this->denyUnlessCanView($workspace);

        $workspaceForm = $this->createForm(InformationSheetWorkspaceType::class, $workspace);
        $workspaceForm->handleRequest($request);

        if ($workspaceForm->isSubmitted() && $workspaceForm->isValid()) {
            $workspace->setUpdatedAt(new \DateTimeImmutable());
            $this->handleAttachments($workspaceForm->get('attachments')->getData(), $workspace, $entityManager, $slugger);
            $entityManager->flush();
            $this->addFlash('success', 'Dossier de travail mis à jour.');

            return $this->redirectToRoute('information_sheet_workspace_show', ['id' => $workspace->getId()]);
        }

        $message = new InformationSheetWorkspaceMessage();
        $messageForm = $this->createForm(WorkspaceMessageType::class, $message);
        $messageForm->handleRequest($request);

        if ($messageForm->isSubmitted() && $messageForm->isValid()) {
            $message->setWorkspace($workspace);
            $message->setAuthor($this->getAgentUser());
            $entityManager->persist($message);
            $workspace->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Message ajouté à la conversation.');

            return $this->redirectToRoute('information_sheet_workspace_show', ['id' => $workspace->getId()]);
        }

        return $this->render('public/information_sheet/workspace/show.html.twig', [
            'workspace' => $workspace,
            'workspaceForm' => $workspaceForm,
            'messageForm' => $messageForm,
            'attachments' => $attachmentRepository->findForWorkspace($workspace),
            'messages' => $messageRepository->findForWorkspace($workspace),
        ]);
    }

    private function handleAttachments(mixed $files, InformationSheetWorkspace $workspace, EntityManagerInterface $entityManager, SluggerInterface $slugger): void
    {
        foreach (is_iterable($files) ? $files : [] as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = strtolower($slugger->slug($originalFilename)->toString());
            $newFilename = sprintf('%s-%s.%s', $safeFilename ?: 'image', uniqid('', true), $file->guessExtension() ?: 'bin');
            $targetDirectory = $this->getParameter('kernel.project_dir') . '/public' . self::UPLOAD_DIR;

            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0775, true);
            }

            $file->move($targetDirectory, $newFilename);

            $attachment = (new InformationSheetWorkspaceAttachment())
                ->setWorkspace($workspace)
                ->setOriginalName($file->getClientOriginalName())
                ->setPath(self::UPLOAD_DIR . '/' . $newFilename);
            $entityManager->persist($attachment);
        }
    }

    private function denyUnlessCanView(InformationSheetWorkspace $workspace): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($workspace->getAgent()?->getId() !== $this->getAgentUser()->getId()) {
            throw $this->createAccessDeniedException('Ce dossier appartient à un autre agent.');
        }
    }

    private function getAgentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion agent requise.');
        }

        return $user;
    }
}
