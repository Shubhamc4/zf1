<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class StatusCommand extends Command
{
    protected string $signature = 'queue:status {--queue= : Optional queue channel filter}';

    protected string $description = 'View real-time queue metrics and job counts';

    public function handle(): int
    {
        $queueName = $this->hasOption('queue') ? (string) $this->option('queue') : null;
        $stat = Queue::status($queueName);

        $this->line(PHP_EOL.'=== Queue Status ===');
        $this->line("  Queue:        {$stat['queue']}");
        $this->line("  Pending:      {$stat['pending']}");
        $this->line("  Reserved:     {$stat['reserved']}");
        $this->line("  Delayed:      {$stat['delayed']}");
        $this->line("  Total Active: {$stat['total_active']}");
        $this->line("  Failed:       {$stat['failed']}");
        $this->line('===================='.PHP_EOL);

        return 0;
    }
}
