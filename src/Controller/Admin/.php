<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ManualReferenceRow;
use App\Entity\ManualReferenceTable;
use App\Form\ManualReferenceRowType;
use App\Form\ManualReferenceTableType;
use App\Repository\ManualReferenceTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
        return $this->render('admin/manual/reference/list.html.twig', ['tables' => $repository->findBy([], ['position' => 'ASC', 'title' => 'ASC'])]);
    }

    #[Route('/nouveau', name: 'admin_manual_reference_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $table = new ManualReferenceTable();
        $form = $this->createForm(ManualReferenceTableType::class, $table);
        $form->get('columnsJson')->setData($this->encodeJson([['key' => 'code', 'label' => 'Code'], ['key' => 'libelle', 'label' => 'Libellé']]));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $this->handleTable($form, $table, $slugger) && $form->isValid()) {
            $em->persist($table); $em->flush(); $this->addFlash('success', 'Référentiel créé.');
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
    public function edit(Request $request, ManualReferenceTable $table, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ManualReferenceTableType::class, $table);
        $form->get('columnsJson')->setData($this->encodeJson($table->getColumnsDefinition()));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $this->handleTable($form, $table, $slugger) && $form->isValid()) {
            $table->setUpdatedAt(new \DateTimeImmutable()); $em->flush(); $this->addFlash('success', 'Référentiel modifié.');
            return $this->redirectToRoute('admin_manual_reference_show', ['id' => $table->getId()]);
        }
        return $this->render('admin/manual/reference/form.html.twig', ['form' => $form, 'table' => $table, 'isEdit' => true]);
    }

    #[Route('/{id}/lignes/nouvelle', name: 'admin_manual_reference_row_new', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function newRow(Request $request, ManualReferenceTable $table, EntityManagerInterface $em): Response
    {
        $row = (new ManualReferenceRow())->setReferenceTable($table);
        return $this->handleRowForm($request, $row, $table, $em, false);
    }

    #[Route('/{id}/lignes/{rowId}/modifier', name: 'admin_manual_reference_row_edit', requirements: ['id' => '\\d+', 'rowId' => '\\d+'], methods: ['GET', 'POST'])]
    public function editRow(Request $request, ManualReferenceTable $table, int $rowId, EntityManagerInterface $em): Response
    {
        $row = $table->getRows()->filter(fn (ManualReferenceRow $row) => $row->getId() === $rowId)->first();
        if (!$row instanceof ManualReferenceRow) { throw $this->createNotFoundException('Ligne introuvable.'); }
        return $this->handleRowForm($request, $row, $table, $em, true);
    }

    private function handleTable(FormInterface $form, ManualReferenceTable $table, SluggerInterface $slugger): bool
    {
        $columns = json_decode((string) $form->get('columnsJson')->getData(), true);
        if (!is_array($columns)) { $form->get('columnsJson')->addError(new FormError('JSON invalide.')); return false; }
        $table->setColumnsDefinition($columns);
        $table->setSlug($slugger->slug($table->getSlug() !== '' ? $table->getSlug() : $table->getTitle())->lower()->toString());
        return true;
    }

    private function handleRowForm(Request $request, ManualReferenceRow $row, ManualReferenceTable $table, EntityManagerInterface $em, bool $isEdit): Response
    {
        $form = $this->createForm(ManualReferenceRowType::class, $row);
        $form->get('dataJson')->setData($this->encodeJson($row->getData()));
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $data = json_decode((string) $form->get('dataJson')->getData(), true);
            if (!is_array($data)) { $form->get('dataJson')->addError(new FormError('JSON invalide.')); }
            else { $row->setData($data); }
            if ($form->isValid()) {
                if ($isEdit) { $row->setUpdatedAt(new \DateTimeImmutable()); } else { $em->persist($row); }
                $table->setUpdatedAt(new \DateTimeImmutable()); $em->flush(); $this->addFlash('success', $isEdit ? 'Ligne modifiée.' : 'Ligne ajoutée.');
                return $this->redirectToRoute('admin_manual_reference_show', ['id' => $table->getId()]);
            }
        }
        return $this->render('admin/manual/reference/row_form.html.twig', ['form' => $form, 'table' => $table, 'row' => $row, 'isEdit' => $isEdit]);
    }

    private function encodeJson(array $data): string { return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
}
