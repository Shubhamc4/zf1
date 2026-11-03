<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class ForgetCommand extends Command
{
    protected string $signature = 'queue:forget {id : The ID of the failed job or "all"}';

    protected string $description = 'Delete failed job(s) by ID or "all"';

    public function handle(): int
    {
        $target = $this->argument(0) ?? $this->option('id');

        if (empty($target)) {
            $this->error('Please specify a job ID or "all". Example: php zf queue:forget 5');

            return 1;
        }

        if ($target === 'all') {
            Queue::flush('failed');
            $this->info('All failed jobs have been deleted.');
        } else {
            $id = (int) $target;
            if (Queue::forgetFailed($id)) {
                $this->info("Failed job #{$id} deleted.");
            } else {
                $this->error("Failed job #{$id} not found.");

                return 1;
            }
        }

        return 0;
    }
}
