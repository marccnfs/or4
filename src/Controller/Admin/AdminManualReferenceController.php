<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ManualReferenceRow;
use App\Entity\ManualReferenceTable;
use App\Form\ManualReferenceRowType;
use App\Form\ManualReferenceTableType;
use App\Repository\ManualReferenceRowRepository;
use App\Repository\ManualReferenceTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/manuel/referentiels')]
class AdminManualReferenceController extends AbstractController
{
    #[Route('', name: 'admin_manual_reference_list', methods: ['GET'])]
    public function list(ManualReferenceTableRepository $repository): Response
    {
        return $this->render('admin/manual/reference/list.html.twig', [
            'tables' => $repository->findBy([], ['position' => 'ASC', 'title' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_manual_reference_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $table = new ManualReferenceTable();
        $form = $this->createForm(ManualReferenceTableType::class, $table);
        $form->get('columnsJson')->setData($this->encodeJson($table->getColumnsDefinition()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $columns = $this->decodeJsonField($form, 'columnsJson', 'Définition de colonnes invalide.');
            if ($columns === null) {
                return $this->render('admin/manual/reference/form.html.twig', ['form' => $form, 'table' => $table, 'isEdit' => false]);
            }

            $this->prepareTable($table, $columns, $slugger, false);
            $entityManager->persist($table);
            $entityManager->flush();
            $this->addFlash('success', 'Référentiel créé.');

            return $this->redirectToRoute('admin_manual_reference_show', ['id' => $table->getId()]);
        }

        return $this->render('admin/manual/reference/form.html.twig', ['form' => $form, 'table' => $table, 'isEdit' => false]);
    }

    #[Route('/{id}', name: 'admin_manual_reference_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(ManualReferenceTable $table): Response
    {
        return $this->render('admin/manual/reference/show.html.twig', ['table' => $table]);
    }

    #[Route('/{id}/modifier', name: 'admin_manual_reference_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ManualReferenceTable $table, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ManualReferenceTableType::class, $table);
        $form->get('columnsJson')->setData($this->encodeJson($table->getColumnsDefinition()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $columns = $this->decodeJsonField($form, 'columnsJson', 'Définition de colonnes invalide.');
            if ($columns === null) {
                return $this->render('admin/manual/reference/form.html.twig', ['form' => $form, 'table' => $table, 'isEdit' => true]);
            }

            $this->prepareTable($table, $columns, $slugger, true);
            $entityManager->flush();
            $this->addFlash('success', 'Référentiel modifié.');

            return $this->redirectToRoute('admin_manual_reference_show', ['id' => $table->getId()]);
        }

        return $this->render('admin/manual/reference/form.html.twig', ['form' => $form, 'table' => $table, 'isEdit' => true]);
    }

    #[Route('/{id}/lignes/nouvelle', name: 'admin_manual_reference_row_new', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function newRow(Request $request, ManualReferenceTable $table, EntityManagerInterface $entityManager): Response
    {
        $row = (new ManualReferenceRow())->setReferenceTable($table);
        $form = $this->createForm(ManualReferenceRowType::class, $row);
        $form->get('dataJson')->setData($this->encodeJson($row->getData()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $this->decodeJsonField($form, 'dataJson', 'Données de ligne invalides.');
            if ($data === null) {
                return $this->render('admin/manual/reference/row_form.html.twig', ['form' => $form, 'table' => $table, 'row' => $row, 'isEdit' => false]);
            }

            $row->setData($data);
            $entityManager->persist($row);
            $entityManager->flush();
            $this->addFlash('success', 'Ligne ajoutée.');

            return $this->redirectToRoute('admin_manual_reference_show', ['id' => $table->getId()]);
        }

        return $this->render('admin/manual/reference/row_form.html.twig', ['form' => $form, 'table' => $table, 'row' => $row, 'isEdit' => false]);
    }

    #[Route('/{id}/lignes/{rowId}/modifier', name: 'admin_manual_reference_row_edit', requirements: ['id' => '\\d+', 'rowId' => '\\d+'], methods: ['GET', 'POST'])]
    public function editRow(Request $request, ManualReferenceTable $table, int $rowId, ManualReferenceRowRepository $rowRepository, EntityManagerInterface $entityManager): Response
    {
        $row = $rowRepository->find($rowId);
        if (!$row instanceof ManualReferenceRow || $row->getReferenceTable()?->getId() !== $table->getId()) {
            throw $this->createNotFoundException('Ligne introuvable.');
        }

        $form = $this->createForm(ManualReferenceRowType::class, $row);
        $form->get('dataJson')->setData($this->encodeJson($row->getData()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $this->decodeJsonField($form, 'dataJson', 'Données de ligne invalides.');
            if ($data === null) {
                return $this->render('admin/manual/reference/row_form.html.twig', ['form' => $form, 'table' => $table, 'row' => $row, 'isEdit' => true]);
            }

            $row->setData($data)->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Ligne modifiée.');

            return $this->redirectToRoute('admin_manual_reference_show', ['id' => $table->getId()]);
        }

        return $this->render('admin/manual/reference/row_form.html.twig', ['form' => $form, 'table' => $table, 'row' => $row, 'isEdit' => true]);
    }

    /** @return array<mixed>|null */
    private function decodeJsonField(FormInterface $form, string $field, string $message): ?array
    {
        try {
            $decoded = json_decode((string) $form->get($field)->getData(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->addFlash('error', $message);
            return null;
        }

        if (!is_array($decoded)) {
            $this->addFlash('error', $message);
            return null;
        }

        return $decoded;
    }

    /** @param array<mixed> $data */
    private function encodeJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /** @param array<mixed> $columns */
    private function prepareTable(ManualReferenceTable $table, array $columns, SluggerInterface $slugger, bool $isEdit): void
    {
        $slugSource = $table->getSlug() !== '' ? $table->getSlug() : $table->getTitle();
        $table->setSlug($slugger->slug($slugSource)->lower()->toString());
        $table->setColumnsDefinition($columns);

        if ($isEdit) {
            $table->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
