<?php

namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ia/chat')]
class Or4publicController extends AbstractController
{
    #[Route('')]
    public function cluster(): Response
    {
        //return $this->render('basepixi1.html.twig');
        return $this->render('newchat.html.twig');
    }

    #[Route('/api')]   //todo me rappel quelle version, peut etre avec pixi, a verifier
    public function api(): Response
    {
        //return $this->render('basepixi1.html.twig');
        return $this->render('chat.html.twig');
    }

    #[Route('/chatpotin')]
    public function achatpotin(): Response
    {
        //return $this->render('basepixi1.html.twig');
        return $this->render('chatpotin.html.twig');
    }
}