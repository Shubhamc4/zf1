<?php

declare(strict_types=1);

namespace ZF1\Schedule;

use DateTime;
use DateTimeInterface;

/**
 * Task Scheduler.
 */
final class Schedule
{
    /** @var array<Event> */
    private array $events = [];

    /**
     * Schedule a CLI (zf) command.
     *
     * Example: $schedule->command('db:backup --days=14')->dailyAt('00:00');
     */
    public function command(string $command): Event
    {
        $event = new Event('command', $command);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Schedule a raw system/shell command.
     *
     * Example: $schedule->exec('bash bin/compress.sh')->weeklyOn(6, '00:00');
     */
    public function exec(string $command): Event
    {
        $event = new Event('exec', $command);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Schedule an in-process PHP callback / closure.
     *
     * Example: $schedule->call(function() { ... })->everyFiveMinutes();
     */
    public function call(callable $callback): Event
    {
        $event = new Event('call', $callback);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Schedule a background Queue Job.
     *
     * Example: $schedule->job(ConsolidateStockJob::class, ['warehouse_id' => 1])->daily();
     */
    public function job(string $jobClass, array $payload = []): Event
    {
        $event = new Event('job', $jobClass, $payload);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Load schedule definitions from application/configs/schedule.php.
     */
    public static function load(?string $filePath = null): static
    {
        $schedule = new self;
        $path = $filePath ?: config_path('schedule.php');

        if (file_exists($path)) {
            $callback = require $path;
            if (is_callable($callback)) {
                $callback($schedule);
            }
        }

        return $schedule;
    }

    /**
     * Return all registered events.
     *
     * @return array<Event>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Find a registered scheduled event by its explicit name or summary.
     */
    public function find(string $name): ?Event
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        foreach ($this->events as $event) {
            if ($event->getName() === $name || $event->getSummary() === $name) {
                return $event;
            }
        }

        foreach ($this->events as $event) {
            if (strcasecmp((string) $event->getName(), $name) === 0 || strcasecmp($event->getSummary(), $name) === 0) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Return all events that are due to run at the given timestamp.
     *
     * @return array<Event>
     */
    public function dueEvents(?DateTimeInterface $date = null): array
    {
        $date ??= new DateTime;

        return array_values(array_filter($this->events, fn (Event $e) => $e->isDue($date)));
    }

    /**
     * Run all due scheduled events.
     *
     * @param  callable|null  $outputCallback  fn(string $line)
     * @return array<int, array{event: Event, result: array{status: string, output: string, duration: float}}>
     */
    public function run(?DateTimeInterface $date = null, ?callable $outputCallback = null): array
    {
        $date ??= new DateTime;
        $due = $this->dueEvents($date);
        $results = [];

        $log = $outputCallback ?: function (string $msg) {
            echo $msg;
        };

        if (empty($due)) {
            $log("No scheduled commands are ready to run.\n");

            return $results;
        }

        $log(sprintf("[%s] Running %d scheduled task(s):\n", $date->format('Y-m-d H:i:s'), count($due)));

        foreach ($due as $event) {
            $summary = $event->getSummary();
            $log(sprintf('  ▶ Running: %s ... ', $summary));

            $res = $event->run();
            $results[] = [
                'event' => $event,
                'result' => $res,
            ];

            if ($res['status'] === 'skipped') {
                $log(sprintf("[SKIPPED] (%s)\n", $res['output']));
            } elseif ($res['status'] === 'failed') {
                $log(sprintf("[FAILED] (%0.2fs) - Error: %s\n", $res['duration'], $res['output']));
            } else {
                $log(sprintf("[OK] (%0.2fs)\n", $res['duration']));
                if (! empty($res['output'])) {
                    $indented = preg_replace('/^/m', '     | ', $res['output']);
                    $log("{$indented}\n");
                }
            }
        }

        return $results;
    }
}
