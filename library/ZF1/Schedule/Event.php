<?php

declare(strict_types=1);

namespace ZF1\Schedule;

use Closure;
use DateTimeInterface;
use RuntimeException;
use Throwable;
use ZF1\Logging\DiscordNotifier;

final class Event
{
    /** @var string Event type: 'command', 'exec', 'call', 'job' */
    private string $type;

    /** @var mixed Command string, callable, or job class */
    private mixed $action;

    /** @var array<string, mixed> Arguments or payload */
    private array $payload = [];

    private string $expression = '* * * * *';

    private ?string $name = null;

    private ?string $description = null;

    private bool $withoutOverlapping = false;

    private int $expiresAtMinutes = 1440;

    private bool $runInBackground = false;

    /** @var array<callable> */
    private array $filters = [];

    /** @var array<callable> */
    private array $rejects = [];

    /** @var array<callable> */
    private array $beforeCallbacks = [];

    /** @var array<callable> */
    private array $afterCallbacks = [];

    public function __construct(string $type, mixed $action, array $payload = [])
    {
        $this->type = $type;
        $this->action = $action;
        $this->payload = $payload;
    }

    // =========================================================================
    // Fluent Scheduling Intervals
    // =========================================================================

    public function cron(string $expression): self
    {
        $this->expression = trim($expression);

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyThreeMinutes(): self
    {
        return $this->cron('*/3 * * * *');
    }

    public function everyFourMinutes(): self
    {
        return $this->cron('*/4 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        $segments = explode(':', $time);
        $hour = (int) ($segments[0] ?? 0);
        $minute = (int) ($segments[1] ?? 0);

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, string $time = '0:0'): self
    {
        $segments = explode(':', $time);
        $hour = (int) ($segments[0] ?? 0);
        $minute = (int) ($segments[1] ?? 0);

        return $this->cron("{$minute} {$hour} * * {$dayOfWeek}");
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $dayOfMonth, string $time = '0:0'): self
    {
        $segments = explode(':', $time);
        $hour = (int) ($segments[0] ?? 0);
        $minute = (int) ($segments[1] ?? 0);

        return $this->cron("{$minute} {$hour} {$dayOfMonth} * *");
    }

    public function quarterly(): self
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    public function weekdays(): self
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    public function weekends(): self
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    public function mondays(): self
    {
        return $this->spliceIntoPosition(5, '1');
    }

    public function tuesdays(): self
    {
        return $this->spliceIntoPosition(5, '2');
    }

    public function wednesdays(): self
    {
        return $this->spliceIntoPosition(5, '3');
    }

    public function thursdays(): self
    {
        return $this->spliceIntoPosition(5, '4');
    }

    public function fridays(): self
    {
        return $this->spliceIntoPosition(5, '5');
    }

    public function saturdays(): self
    {
        return $this->spliceIntoPosition(5, '6');
    }

    public function sundays(): self
    {
        return $this->spliceIntoPosition(5, '0');
    }

    private function spliceIntoPosition(int $position, string $value): self
    {
        $segments = preg_split('/\s+/', $this->expression);
        $segments[$position - 1] = $value;
        $this->expression = implode(' ', $segments);

        return $this;
    }

    // =========================================================================
    // Options & Hooks
    // =========================================================================

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the unique or human-friendly name for this event.
     */
    public function name(string $name): self
    {
        $this->name = trim($name);
        $this->description ??= $this->name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function withoutOverlapping(int $expiresAtMinutes = 1440): self
    {
        $this->withoutOverlapping = true;
        $this->expiresAtMinutes = $expiresAtMinutes;

        return $this;
    }

    public function runInBackground(): self
    {
        $this->runInBackground = true;

        return $this;
    }

    public function when(callable $callback): self
    {
        $this->filters[] = $callback;

        return $this;
    }

    public function skip(callable $callback): self
    {
        $this->rejects[] = $callback;

        return $this;
    }

    public function before(callable $callback): self
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    // =========================================================================
    // Evaluation & Execution
    // =========================================================================

    public function isDue(?DateTimeInterface $date = null): bool
    {
        $cron = new CronExpression($this->expression);
        if (! $cron->isDue($date)) {
            return false;
        }

        foreach ($this->filters as $filter) {
            if (! $filter()) {
                return false;
            }
        }

        foreach ($this->rejects as $reject) {
            if ($reject()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run the scheduled event.
     *
     * @return array{status: string, output: string, duration: float}
     */
    public function run(): array
    {
        $lockFile = null;
        if ($this->withoutOverlapping) {
            $lockFile = $this->getLockFilePath();
            if ($this->hasActiveLock($lockFile)) {
                return [
                    'status' => 'skipped',
                    'output' => "Skipped: Previous instance of '{$this->getSummary()}' is still running (lock active).",
                    'duration' => 0.0,
                ];
            }
            $this->acquireLock($lockFile);
        }

        foreach ($this->beforeCallbacks as $cb) {
            $cb();
        }

        $startTime = microtime(true);
        $output = '';
        $status = 'success';

        try {
            switch ($this->type) {
                case 'command':
                    $phpBinary = escapeshellarg(PHP_BINARY ?: 'php');
                    $cliScript = base_path('zf');
                    $escapedScript = escapeshellarg($cliScript);
                    $fullCmd = "{$phpBinary} {$escapedScript} {$this->action}";
                    if ($this->runInBackground) {
                        $fullCmd .= ' > /dev/null 2>&1 &';
                        shell_exec($fullCmd);
                        $output = 'Started in background.';
                    } else {
                        $output = (string) shell_exec($fullCmd.' 2>&1');
                    }
                    break;

                case 'exec':
                    $cmd = (string) $this->action;
                    if ($this->runInBackground) {
                        $cmd .= ' > /dev/null 2>&1 &';
                        shell_exec($cmd);
                        $output = 'Started in background.';
                    } else {
                        $output = (string) shell_exec($cmd.' 2>&1');
                    }
                    break;

                case 'call':
                    ob_start();
                    $result = ($this->action)();
                    $echoed = ob_get_clean();
                    $output = $echoed.($result !== null ? (is_string($result) ? $result : json_encode($result)) : '');
                    break;

                case 'job':
                    $jobClass = (string) $this->action;
                    if (method_exists($jobClass, 'queue')) {
                        $jobId = $jobClass::queue($this->payload);
                        $output = $jobId > 0 ? "Queued {$jobClass} #{$jobId}" : "Executed {$jobClass} (sync mode)";
                    } else {
                        throw new RuntimeException("Job class '{$jobClass}' does not have a queue() method.");
                    }
                    break;
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $output = $e->getMessage();

            // Alert via Discord if configured
            try {
                DiscordNotifier::error(
                    '🚨 Scheduled Task Failed',
                    "Task '{$this->getSummary()}' failed during execution: {$e->getMessage()}",
                    [
                        'Expression' => "`{$this->expression}`",
                        'Type' => $this->type,
                    ]
                );
            } catch (Throwable) {
                // Ignore Discord alert failure
            }
        } finally {
            if ($lockFile !== null) {
                $this->releaseLock($lockFile);
            }
        }

        $duration = round(microtime(true) - $startTime, 3);

        foreach ($this->afterCallbacks as $cb) {
            $cb($output, $status);
        }

        return [
            'status' => $status,
            'output' => trim($output),
            'duration' => $duration,
        ];
    }

    // =========================================================================
    // Lock Management (withoutOverlapping)
    // =========================================================================

    public function getLockFilePath(): string
    {
        $lockDir = ensure_directory_exists(storage_path('data/locks'));

        if (is_string($this->action)) {
            $identifier = $this->action;
        } elseif (is_object($this->action) && $this->action instanceof Closure) {
            $identifier = spl_object_hash($this->action);
        } else {
            $identifier = json_encode($this->action) ?: 'closure';
        }

        $hash = md5($this->type.':'.$identifier.':'.($this->description ?? ''));

        return "{$lockDir}/schedule-{$hash}.lock";
    }

    private function hasActiveLock(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        $mtime = filemtime($path);
        if ($mtime !== false && (time() - $mtime) > ($this->expiresAtMinutes * 60)) {
            // Lock expired, clean up
            @unlink($path);

            return false;
        }

        return true;
    }

    private function acquireLock(string $path): void
    {
        @file_put_contents($path, (string) getmypid());
    }

    private function releaseLock(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAction(): mixed
    {
        return $this->action;
    }

    public function getSummary(): string
    {
        if ($this->description) {
            return $this->description;
        }

        if (is_string($this->action)) {
            return $this->action;
        }

        return 'Closure';
    }

    public function isWithoutOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }
}
