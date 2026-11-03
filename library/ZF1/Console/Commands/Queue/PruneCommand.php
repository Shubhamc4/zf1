<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class PruneCommand extends Command
{
    protected string $signature = 'queue:prune {--days=30 : Number of days to retain failed jobs}';

    protected string $description = 'Prune old failed jobs from the dead-letter table';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? 30);
        $deleted = Queue::pruneFailed($days);
        $this->info("Pruned {$deleted} failed job(s) older than {$days} days.");

        return 0;
    }
}
