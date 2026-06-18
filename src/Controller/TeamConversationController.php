<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMembership;
use App\Entity\TeamMessage;
use App\Entity\User;
use App\Form\AgentRegistrationType;
use App\Form\ConversationGroupType;
use App\Form\TeamInvitationType;
use App\Form\TeamMessageType;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMembershipRepository;
use App\Repository\TeamMessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

class TeamConversationController extends AbstractController
{
    private const UPLOAD_DIR = '/uploads/team-conversations';

    #[IsGranted('ROLE_USER')]
    #[Route('/groupes', name: 'team_conversation_home', methods: ['GET'])]
    public function home(TeamMembershipRepository $memberships): Response
    {
        return $this->render('team_conversation/home.html.twig', ['memberships' => $memberships->findForUser($this->getAppUser())]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/groupes/creation', name: 'team_conversation_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $team = (new Team())
            ->setState('conversation')
            ->setRegistrationCode('conv-'.bin2hex(random_bytes(8)))
            ->setQrToken(bin2hex(random_bytes(16)))
            ->setLetterOrder([]);

        $form = $this->createForm(ConversationGroupType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($team);
            $em->persist((new TeamMembership())->setTeam($team)->setUser($this->getAppUser())->setRole('admin'));
            $em->flush();

            $this->addFlash('success', 'Groupe de conversation créé. Vous pouvez maintenant inviter des membres.');

            return $this->redirectToRoute('team_invitation_create', ['id' => $team->getId()]);
        }

        return $this->render('team_conversation/create.html.twig', ['form' => $form]);
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/groupes/{id}/conversation', name: 'team_conversation_show', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, Team $team, TeamMembershipRepository $memberships, TeamMessageRepository $messages, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $user = $this->getAppUser();
        if (!$this->isGranted('ROLE_ADMIN') && !$memberships->isMember($team, $user)) {
            throw $this->createAccessDeniedException('Invitation requise pour accéder à ce groupe.');
        }

        $message = new TeamMessage();
        $form = $this->createForm(TeamMessageType::class, $message);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $image */
            $image = $form->get('image')->getData();
            if ($message->getContent() === null && !$image instanceof UploadedFile) {
                $this->addFlash('error', 'Écrivez un message ou joignez une photo.');
            } else {
                $message->setTeam($team)->setAuthor($user);
                if ($image instanceof UploadedFile) {
                    $this->attachImage($message, $image, $slugger);
                }
                $em->persist($message);
                $em->flush();
                return $this->redirectToRoute('team_conversation_show', ['id' => $team->getId(), '_fragment' => 'dernier-message']);
            }
        }

        return $this->render('team_conversation/show.html.twig', [
            'team' => $team,
            'messages' => $messages->findForTeam($team),
            'members' => $memberships->findForTeam($team),
            'messageForm' => $form,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/groupes/{id}/invitations', name: 'team_invitation_create', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function invite(Request $request, Team $team, EntityManagerInterface $em, MailerInterface $mailer, TeamInvitationRepository $invitations): Response
    {
        $form = $this->createForm(TeamInvitationType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $invitation = (new TeamInvitation())->setTeam($team)->setInvitedBy($this->getAppUser())->setEmail($email);
            $knownUser = $em->getRepository(User::class)->findOneBy(['email' => $invitation->getEmail()]);
            if ($knownUser instanceof User) {
                if (!$em->getRepository(TeamMembership::class)->isMember($team, $knownUser)) {
                    $em->persist((new TeamMembership())->setTeam($team)->setUser($knownUser));
                }
                $invitation->accept();
            }
            $em->persist($invitation);
            $em->flush();
            $url = $this->generateUrl('team_invitation_register', ['token' => $invitation->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
            $mailer->send((new TemplatedEmail())->from(new Address('no-reply@sjdb.space', 'sjdb.space'))->to($invitation->getEmail())->subject('Invitation à rejoindre le groupe '.$team->getName())->htmlTemplate('emails/team_invitation.html.twig')->context(['team' => $team, 'url' => $url, 'knownUser' => $knownUser instanceof User]));
            $this->addFlash('success', $knownUser instanceof User ? 'Utilisateur connu ajouté au groupe et prévenu par email.' : 'Invitation envoyée par email pour créer le compte et rejoindre le groupe.');
            $this->addFlash('success', 'Invitation envoyée par email.');
            return $this->redirectToRoute('team_invitation_create', ['id' => $team->getId()]);
        }
        return $this->render('team_conversation/invite.html.twig', ['team' => $team, 'form' => $form, 'invitations' => $invitations->findRecentForTeam($team)]);
    }

    #[Route('/invitation/groupe/{token}', name: 'team_invitation_register', methods: ['GET', 'POST'])]
    public function registerFromInvitation(string $token, Request $request, TeamInvitationRepository $invitations, UserRepository $users, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $invitation = $invitations->findOneBy(['token' => $token]);
        if (!$invitation instanceof TeamInvitation) { throw $this->createNotFoundException('Invitation introuvable.'); }
        $user = $users->findOneBy(['email' => $invitation->getEmail()]) ?? new User();
        $isNewUser = $user->getId() === null;
        if ($isNewUser) {
            $user->setEmail($invitation->getEmail())->setRoles(['ROLE_GUEST']);
        }
        $form = $this->createForm(AgentRegistrationType::class, $user, ['email_locked' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNewUser) {
                $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $em->persist($user);
            }
            if (!$invitation->isAccepted()) { $invitation->accept(); }
            if (!$em->getRepository(TeamMembership::class)->isMember($invitation->getTeam(), $user)) {
                $em->persist((new TeamMembership())->setTeam($invitation->getTeam())->setUser($user));
            }
            $em->flush();
            $this->addFlash('success', 'Bienvenue dans le groupe. Connectez-vous pour accéder à la conversation.');
            return $this->redirectToRoute('app_login');
        }
        return $this->render('team_conversation/register.html.twig', ['registrationForm' => $form, 'invitation' => $invitation]);
    }

    private function attachImage(TeamMessage $message, UploadedFile $file, SluggerInterface $slugger): void
    {
        $base = strtolower($slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->toString()) ?: 'photo';
        $name = sprintf('%s-%s.%s', $base, uniqid('', true), $file->guessExtension() ?: 'bin');
        $dir = $this->getParameter('kernel.project_dir').'/public'.self::UPLOAD_DIR;
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        $file->move($dir, $name);
        $message->setImagePath(self::UPLOAD_DIR.'/'.$name)->setImageOriginalName($file->getClientOriginalName());
    }

    private function getAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) { throw $this->createAccessDeniedException('Connexion requise.'); }
        return $user;
    }
}
