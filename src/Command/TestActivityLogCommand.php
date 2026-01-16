<?php
// src/Command/TestActivityLogCommand.php

namespace App\Command;

use App\Entity\User;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-activity-log')]
class TestActivityLogCommand extends Command
{
    public function __construct(
        private ActivityLoggerService $activityLogger,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🧪 Testing Activity Log System...');
        
        // Get admin user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'admin@binscafe.com']);

        if (!$user) {
            $output->writeln('<error>❌ Admin user not found!</error>');
            return Command::FAILURE;
        }

        $output->writeln('✅ Found user: ' . $user->getEmail());

        // Test logging
        try {
            $this->activityLogger->log('test', 'TestEntity', 999, 'Testing activity log system', $user);
            $output->writeln('<info>✅ Activity log created successfully!</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}