<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Schedule;

use DateTime;
use ZF1\Console\Command;
use ZF1\Schedule\CronExpression;
use ZF1\Schedule\Schedule;

final class ListCommand extends Command
{
    protected string $name = 'schedule:list';

    protected string $description = 'List all configured scheduled tasks, intervals and status';

    public function handle(): int
    {
        $schedule = Schedule::load();
        $events = $schedule->events();

        if (empty($events)) {
            $this->comment('No scheduled tasks configured.');

            return 0;
        }

        $now = new DateTime;
        $this->line(PHP_EOL."\033[33m=== Configured Scheduled Tasks (".count($events).") ===\033[0m");

        $headers = ['Name / Action', 'Expression', 'Interval', 'Next Run', 'Concurrency'];
        $rows = [];

        foreach ($events as $event) {
            $cron = new CronExpression($event->getExpression());
            $name = $event->getName();
            $summary = $event->getSummary();
            $displayName = $name !== null ? ($name === $summary ? $name : "{$name} [{$summary}]") : $summary;

            if (mb_strwidth($displayName) > 40) {
                $displayName = mb_substr($displayName, 0, 37).'...';
            }

            $locking = $event->isWithoutOverlapping() ? 'No Overlap' : 'Standard';

            $nextRunHuman = $cron->getNextRunHuman($now);
            $nextDate = $cron->getNextRunDate($now);
            $timeStr = $nextDate->format('H:i');
            $dateStr = $nextDate->format('Y-m-d') === $now->format('Y-m-d') ? $timeStr : $nextDate->format('M j, H:i');
            $nextDisplay = $event->isDue($now) ? 'due now' : "{$nextRunHuman} ({$dateStr})";

            $rows[] = [
                $displayName,
                $event->getExpression(),
                $cron->toHumanString(),
                $nextDisplay,
                $locking,
            ];
        }

        $this->table($headers, $rows);
        $this->line('');
        $this->comment('Tip: Run any scheduled task on-demand anytime with: php zf schedule:run <name>');
        $this->line('');

        return 0;
    }
}
