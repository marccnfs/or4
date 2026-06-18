<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SgbdPublicController extends AbstractController
{
    #[Route('', name: 'index_public', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_AGENT')) {
            return $this->redirectToRoute('team_conversation_home');
        }
        return $this->render('public/index.html.twig');
    }
}