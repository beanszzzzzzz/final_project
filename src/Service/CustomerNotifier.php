<?php

namespace App\Service;

use App\Entity\Customer;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;

class CustomerNotifier
{
    private ?HubInterface $hub;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, ?HubInterface $hub = null)
    {
        $this->hub = $hub;
        $this->logger = $logger;
    }

    public function publish(Customer $customer, string $action = 'updated'): void
    {
        if ($this->hub === null) {
            $this->logger->debug('Mercure hub not available; skipping publish for Customer '.$customer->getId());
            // still write fallback event for SSE subscribers
            $this->publishRaw([
                'action' => $action,
                'customer' => [
                    'id' => $customer->getId(),
                    'name' => $customer->getName(),
                    'email' => $customer->getEmail(),
                ],
                'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
            return;
        }

        $data = json_encode([
            'action' => $action,
            'customer' => [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'address' => $customer->getAddress(),
            ],
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        try {
            $update = new Update('/customers/'.$customer->getId(), $data);
            $this->hub->publish($update);
            $this->logger->info('Published Mercure update for customer '.$customer->getId());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish Mercure update: '.$e->getMessage());
        }
    }

    public function publishRaw(array $payload): void
    {
        $path = dirname(__DIR__, 2).'/var/mercure_fallback.log';
        @mkdir(dirname($path), 0777, true);
        $line = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $this->logger->error('Failed to encode fallback payload');
            return;
        }
        file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
        $this->logger->info('Appended fallback mercure event');
    }
}
