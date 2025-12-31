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
        return $this->render('public/index.html.twig');
    }
}