<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Schedule;

use DateTime;
use ZF1\Console\Command;
use ZF1\Schedule\Schedule;

final class WorkCommand extends Command
{
    protected string $signature = 'schedule:work {--run-once : Run due tasks once and exit}';

    protected string $description = 'Run the scheduler daemon locally (executes due tasks every minute)';

    public function handle(): int
    {
        $this->info('Schedule Worker started (Press Ctrl+C to stop)...');
        $this->line('Running scheduled tasks every minute...'.PHP_EOL);

        while (true) {
            $now = new DateTime;
            $schedule = Schedule::load();
            $due = $schedule->dueEvents($now);

            if (! empty($due)) {
                $schedule->run($now);
            }

            if ($this->hasOption('run-once')) {
                break;
            }

            $second = (int) date('s');
            $sleepSeconds = max(1, 60 - $second);
            sleep($sleepSeconds);
        }

        return 0;
    }
}
