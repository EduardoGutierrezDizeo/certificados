<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CertificatesQueueStatus extends Command
{
    protected $signature = 'certificates:queue-status';

    protected $description = 'Muestra la cantidad de jobs pendientes en la cola de certificados (Redis)';

    private const QUEUE_NAME = 'certificate_jobs';

    public function handle(): int
    {
        $pending = Redis::llen(self::QUEUE_NAME);

        $this->line("Jobs pendientes en '".self::QUEUE_NAME."': {$pending}");

        if ($pending > 0) {
            $this->info("Hay {$pending} certificado(s) esperando ser procesado(s).");
        } else {
            $this->info('La cola está vacía.');
        }

        return self::SUCCESS;
    }
}
