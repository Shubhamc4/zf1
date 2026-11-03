<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use ZF1\Console\Command;
use ZF1\Queue\Worker;

final class WorkCommand extends Command
{
    protected string $signature = 'queue:work {--queue=default : Queue channel to listen on} {--sleep=3 : Seconds to wait when no job available} {--tries=3 : Max retry attempts} {--max-jobs=0 : Max jobs before graceful shutdown} {--memory-limit=128 : Memory limit in megabytes} {--stop-when-empty : Stop worker when queue is empty}';

    protected array $aliases = ['queue'];

    protected string $description = 'Start the background queue worker process';

    public function handle(): int
    {
        Worker::run($this->options);

        return 0;
    }
}
