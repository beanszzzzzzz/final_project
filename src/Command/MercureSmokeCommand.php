<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;
use App\Service\CustomerNotifier;

class MercureSmokeCommand extends Command
{
    protected static $defaultName = 'app:mercure:smoke';

    private HubInterface $hub;
    private LoggerInterface $logger;
    private CustomerNotifier $notifier;

    public function __construct(HubInterface $hub, LoggerInterface $logger, CustomerNotifier $notifier)
    {
        parent::__construct();
        $this->hub = $hub;
        $this->logger = $logger;
        $this->notifier = $notifier;
    }

    protected function configure(): void
    {
        $this->setName('app:mercure:smoke');
        $this->setDescription('Publish a smoke test update to /customers');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payload = [
            'smoke' => 'ok',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $update = new Update('/customers', json_encode($payload));

        try {
            $this->hub->publish($update);
            $output->writeln('Published smoke update to /customers');
            $this->logger->info('Published Mercure smoke update', $payload);
        } catch (\Throwable $e) {
            $output->writeln('Failed to publish update: '.$e->getMessage());
            $this->logger->error('Failed to publish Mercure smoke update: '.$e->getMessage());
            $this->notifier->publishRaw($payload);
            $output->writeln('Wrote fallback event to var/mercure_fallback.log');
        }

        return Command::SUCCESS;
    }
}
