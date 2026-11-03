<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class FailedCommand extends Command
{
    protected string $name = 'queue:failed';

    protected string $description = 'List all failed jobs in the dead-letter table';

    public function handle(): int
    {
        $failedJobs = Queue::getFailed();

        if (empty($failedJobs)) {
            $this->info('No failed jobs found.');

            return 0;
        }

        $headers = ['ID', 'Queue', 'Class', 'Failed At', 'Exception Preview'];
        $rows = [];

        foreach ($failedJobs as $job) {
            $payload = json_decode((string) ($job['payload'] ?? ''), true);
            $className = basename(str_replace('\\', '/', (string) ($payload['job'] ?? 'Unknown')));
            $preview = mb_strimwidth(str_replace(["\r", "\n"], ' ', (string) ($job['exception'] ?? '')), 0, 45, '...');

            $rows[] = [
                $job['id'],
                $job['queue'],
                $className,
                $job['failed_at'],
                $preview,
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
