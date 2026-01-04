<?php

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class MercureListenController extends AbstractController
{
    #[Route('/_dev/mercure/listen', name: 'dev_mercure_listen', methods: ['GET'])]
    public function listen(): Response
    {
        return $this->render('dev/mercure_listen.html.twig');
    }
}
