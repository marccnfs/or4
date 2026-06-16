<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AgentRegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AgentRegistrationController extends AbstractController
{
    #[Route('/register', name: 'agent_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('information_sheet_workspace_index');
        }

        $agent = new User();
        $agent->setRoles(['ROLE_USER']);
        $form = $this->createForm(AgentRegistrationType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($userRepository->findOneBy(['email' => $agent->getEmail()]) !== null) {
                $this->addFlash('error', 'Un compte existe déjà pour cet email.');
            } else {
                $plainPassword = (string) $form->get('plainPassword')->getData();
                $agent->setPassword($passwordHasher->hashPassword($agent, $plainPassword));

                $entityManager->persist($agent);
                $entityManager->flush();

                $this->addFlash('success', 'Compte agent créé. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
