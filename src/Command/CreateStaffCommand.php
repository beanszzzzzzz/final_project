<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-staff',
    description: 'Create a staff user for Bins Cafe',
)]
class CreateStaffCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Create Staff User - Bins Cafe');

        $email = 'staff@binscafe.com';
        $password = 'staff123';

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser) {
            $io->error('Staff user already exists.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_STAFF']);
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setVerifiedAt(new \DateTime());

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Staff user created successfully.');
        $io->text([
            'Email: ' . $email,
            'Password: ' . $password,
            'Role: ROLE_STAFF',
        ]);
        $io->warning('IMPORTANT: Change the password immediately after first login.');
        $io->note('Login at: /login');

        return Command::SUCCESS;
    }
}
