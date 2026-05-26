<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:users',
    description: 'Seeds the database with default test users',
)]
class SeedUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = [
            [
                'email' => 'admin@binscafe.com',
                'password' => 'Admin123!',
                'roles' => ['ROLE_ADMIN'],
                'name' => 'Administrator',
            ],
            [
                'email' => 'staff@binscafe.com',
                'password' => 'Staff123!',
                'roles' => ['ROLE_STAFF'],
                'name' => 'Staff Member',
            ],
            [
                'email' => 'customer@binscafe.com',
                'password' => 'Customer123!',
                'roles' => ['ROLE_USER'],
                'name' => 'Customer',
            ],
        ];

        $verifiedAt = new \DateTimeImmutable();

        foreach ($users as $userData) {
            // Check if user already exists
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userData['email']]);

            if ($existingUser) {
                $output->writeln("✓ User {$userData['email']} already exists, skipping...");
                continue;
            }

            $user = new User();
            $user->setEmail($userData['email']);
            $user->setRoles($userData['roles']);
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $user->setVerifiedAt($verifiedAt);

            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $userData['password']
            );
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $output->writeln("✓ Created user: {$userData['email']}");
        }

        $this->entityManager->flush();
        $output->writeln("\n✅ Database seeding complete!");

        return Command::SUCCESS;
    }
}
