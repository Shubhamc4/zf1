<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Schedule;

use ZF1\Console\Command;
use ZF1\Schedule\Schedule;

final class RunCommand extends Command
{
    protected string $signature = 'schedule:run {name? : Optional name or action of a specific scheduled task to run on-demand}';

    protected string $description = 'Run due scheduled tasks, or run a specific scheduled task on-demand by name';

    public function handle(): int
    {
        $schedule = Schedule::load();
        $targetName = $this->argument('name');

        // 1. Run a specific task on-demand if name argument was provided
        if (! empty($targetName) && is_string($targetName)) {
            $event = $schedule->find($targetName);
            if (! $event) {
                $this->error("Scheduled task [{$targetName}] was not found.");
                $this->line('');
                $events = $schedule->events();
                if (empty($events)) {
                    $this->comment('No scheduled tasks are configured.');
                } else {
                    $this->comment('Available scheduled tasks:');
                    foreach ($events as $e) {
                        $identifier = $e->getName() ?: $e->getSummary();
                        $this->line("  - \033[32m{$identifier}\033[0m (cron: {$e->getExpression()})");
                    }
                }

                return 1;
            }

            $displayName = $event->getName() ?: $event->getSummary();
            $this->comment("Running scheduled task [{$displayName}] on-demand...");
            $res = $event->run();

            if ($res['status'] === 'skipped') {
                $this->warn("Task skipped: {$res['output']}");

                return 0;
            }

            if ($res['status'] === 'failed') {
                $this->error("Task failed ({$res['duration']}s): {$res['output']}");

                return 1;
            }

            $this->info("Task completed successfully ({$res['duration']}s).");
            if (! empty($res['output'])) {
                $this->line($res['output']);
            }

            return 0;
        }

        // 2. Otherwise run all due tasks
        $due = $schedule->dueEvents();

        if (empty($due)) {
            $this->comment('No scheduled tasks are due to run right now.');

            return 0;
        }

        $this->comment('Running '.count($due).' due scheduled task(s)...');
        $executed = $schedule->run();
        $count = is_array($executed) ? count($executed) : (int) $executed;
        $this->info("Completed {$count} scheduled task(s).");

        return 0;
    }
}
