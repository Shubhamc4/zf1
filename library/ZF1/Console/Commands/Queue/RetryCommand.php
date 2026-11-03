<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class RetryCommand extends Command
{
    protected string $signature = 'queue:retry {id=all : The ID of the failed job or "all"}';

    protected string $description = 'Retry failed job(s) by ID or "all"';

    public function handle(): int
    {
        $target = $this->argument(0) ?? $this->option('id') ?? 'all';

        if ($target === 'all') {
            $retried = Queue::retryFailed();
            $this->info("Retried {$retried} failed job(s).");
        } else {
            $id = (int) $target;
            if (Queue::retryFailed($id)) {
                $this->info("Failed job #{$id} pushed back to active queue.");
            } else {
                $this->error("Failed job #{$id} not found.");

                return 1;
            }
        }

        return 0;
    }
}
