<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class SseController
{
    #[Route('/sse/customers', name: 'sse_customers')]
    public function customersStream(): StreamedResponse
    {
        $path = dirname(__DIR__, 2).'/var/mercure_fallback.log';

        $response = new StreamedResponse(function () use ($path) {
            set_time_limit(0);
            $start = file_exists($path) ? filesize($path) : 0;
            $fp = fopen($path, 'c+');
            if ($fp === false) {
                echo "data: []\n\n";
                return;
            }
            fseek($fp, $start);
            $end = time() + 30;
            while (time() < $end) {
                clearstatcache(false, $path);
                while (($line = fgets($fp)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    echo 'data: '.$line."\n\n";
                    @ob_flush(); @flush();
                }
                sleep(1);
            }
            fclose($fp);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}
