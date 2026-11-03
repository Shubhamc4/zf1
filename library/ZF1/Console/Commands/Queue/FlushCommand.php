<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class FlushCommand extends Command
{
    protected string $signature = 'queue:flush {type=all : Queue type to flush (active|failed|all)} {--queue= : Optional queue filter}';

    protected string $description = 'Flush jobs from active queue or failed table';

    public function handle(): int
    {
        $type = (string) ($this->argument(0) ?? $this->option('type') ?? 'all');
        $queueName = $this->hasOption('queue') ? (string) $this->option('queue') : null;

        $deleted = Queue::flush($type, $queueName);
        $this->info("Flushed {$deleted} job(s) from '{$type}' queue.");

        return 0;
    }
}
