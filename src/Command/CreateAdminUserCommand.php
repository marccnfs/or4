<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user.',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $io->ask('Email', null, function (?string $value): string {
            $value = trim((string) $value);

            if ($value == '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Please enter a valid email address.');
            }

            if ($this->userRepository->findOneBy(['email' => $value])) {
                throw new \RuntimeException('An account with this email already exists.');
            }

            return $value;
        });

        $plainPassword = $io->askHidden('Password', function (?string $value): string {
            $value = (string) $value;

            if ($value === '') {
                throw new \RuntimeException('Password cannot be empty.');
            }

            if (mb_strlen($value) < 8) {
                throw new \RuntimeException('Password must be at least 8 characters.');
            }

            return $value;
        });

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin user created successfully.');

        return Command::SUCCESS;
    }
}