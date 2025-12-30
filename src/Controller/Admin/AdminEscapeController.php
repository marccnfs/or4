<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/escape')]
class AdminEscapeController extends AbstractController
{
    #[Route('/new', name: 'admin_escape_new')]
    public function new(): Response
    {
        return $this->render('admin/escape/new.html.twig');
    }

    #[Route('/list', name: 'admin_escape_list')]
    public function list(): Response
    {
        return $this->render('admin/escape/list.html.twig');
    }

    #[Route('/{id}', name: 'admin_escape_show')]
    public function show(string $id): Response
    {
        return $this->render('admin/escape/show.html.twig', [
            'escapeId' => $id,
        ]);
    }
}