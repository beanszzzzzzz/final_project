<?php

namespace App\Command;

use App\Service\CustomerNotifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotifySmokeCommand extends Command
{
    protected static $defaultName = 'app:notify:smoke';

    private CustomerNotifier $notifier;

    public function __construct(CustomerNotifier $notifier)
    {
        parent::__construct();
        $this->notifier = $notifier;
    }

    protected function configure(): void
    {
        $this->setName('app:notify:smoke');
        $this->setDescription('Write a fallback SSE event to var/mercure_fallback.log');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payload = [
            'action' => 'smoke',
            'message' => 'fallback event',
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->notifier->publishRaw($payload);
        $output->writeln('Wrote fallback event to var/mercure_fallback.log');

        return Command::SUCCESS;
    }
}
