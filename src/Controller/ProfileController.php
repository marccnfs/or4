<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamMembership;
use App\Entity\User;
use App\Form\AdminAgentCreationType;
use App\Form\ProfileType;
use App\Repository\TeamMembershipRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, TeamRepository $teams, TeamMembershipRepository $memberships, UserRepository $users, UserPasswordHasherInterface $hasher, MailerInterface $mailer): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile');
        }

        $agent = (new User())->setRoles(['ROLE_AGENT']);
        $agentForm = null;
        if ($this->isGranted('ROLE_ADMIN')) {
            $agentForm = $this->createForm(AdminAgentCreationType::class, $agent);
            $agentForm->handleRequest($request);
            if ($agentForm->isSubmitted() && $agentForm->isValid()) {
                if ($users->findOneBy(['email' => $agent->getEmail()]) instanceof User) {
                    $this->addFlash('error', 'Un compte existe déjà pour cet email.');
                } else {
                    $plainPassword = bin2hex(random_bytes(6));
                    $agent->setPassword($hasher->hashPassword($agent, $plainPassword));
                    $entityManager->persist($agent);
                    $entityManager->flush();
                    $mailer->send((new TemplatedEmail())
                        ->from(new Address('no-reply@sjdb.space', 'sjdb.space'))
                        ->to($agent->getEmail())
                        ->subject('Vos accès agent sjdb.space')
                        ->htmlTemplate('emails/agent_credentials.html.twig')
                        ->context(['agent' => $agent, 'plainPassword' => $plainPassword, 'loginUrl' => $this->generateUrl('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)]));
                    $this->addFlash('success', 'Compte agent créé et informations de connexion envoyées par email.');
                    return $this->redirectToRoute('app_profile');
                }
            }
        }

        $conversationTeams = $this->isGranted('ROLE_ADMIN') ? $teams->findByStatus('conversation') : [];

        return $this->render('security/profile.html.twig', [
            'profileForm' => $form,
            'agentForm' => $agentForm,
            'conversationTeams' => $conversationTeams,
            'membershipsByTeam' => $this->isGranted('ROLE_ADMIN') ? $this->buildMembershipMap($conversationTeams, $memberships) : [],
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/profil/admin/groupes/{id}/membres/ajout', name: 'admin_profile_group_member_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addMember(Request $request, Team $team, UserRepository $users, TeamMembershipRepository $memberships, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('add-member-'.$team->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $email = trim((string) $request->request->get('email'));
        $user = $users->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->addFlash('error', 'Aucun compte trouvé pour cet email. Créez d’abord le compte agent ou envoyez une invitation.');
        } elseif ($memberships->isMember($team, $user)) {
            $this->addFlash('error', 'Ce compte est déjà membre du groupe.');
        } else {
            $em->persist((new TeamMembership())->setTeam($team)->setUser($user));
            $em->flush();
            $this->addFlash('success', 'Membre ajouté au groupe.');
        }
        return $this->redirectToRoute('app_profile', ['_fragment' => 'admin-group-'.$team->getId()]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/profil/admin/membres/{id}/{action}', name: 'admin_profile_group_member_action', requirements: ['id' => '\\d+', 'action' => 'remove|block|unblock'], methods: ['POST'])]
    public function memberAction(Request $request, TeamMembership $membership, string $action, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('member-action-'.$membership->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if ($action === 'remove') {
            $em->remove($membership);
            $message = 'Membre supprimé du groupe.';
        } else {
            $membership->setBlocked($action === 'block');
            $message = $action === 'block' ? 'Membre bloqué.' : 'Membre débloqué.';
        }
        $em->flush();
        $this->addFlash('success', $message);
        return $this->redirectToRoute('app_profile');
    }

    /** @return array<int, TeamMembership[]> */
    private function buildMembershipMap(array $teams, TeamMembershipRepository $memberships): array
    {
        $map = [];
        foreach ($teams as $team) {
            $map[$team->getId()] = $memberships->findForTeam($team);
        }
        return $map;
    }
}
