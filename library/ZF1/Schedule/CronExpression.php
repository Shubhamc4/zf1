<?php

declare(strict_types=1);

namespace ZF1\Schedule;

use DateTime;
use DateTimeInterface;

/**
 * Pure PHP 5-part Cron Expression parser and evaluator.
 *
 * Syntax:
 *  *    *    *    *    *
 *  -    -    -    -    -
 *  |    |    |    |    +--- day of week (0 - 6, 0 is Sunday, or 7 is Sunday)
 *  |    |    |    +-------- month (1 - 12)
 *  |    |    +------------- day of month (1 - 31)
 *  |    +------------------ hour (0 - 23)
 *  +----------------------- minute (0 - 59)
 */
final class CronExpression
{
    private string $expression;

    public function __construct(string $expression)
    {
        $this->expression = trim($expression);
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Check if the cron expression matches the specified or current timestamp.
     */
    public function isDue(?DateTimeInterface $date = null): bool
    {
        $date ??= new DateTime;

        $parts = preg_split('/\s+/', $this->expression);
        if (count($parts) !== 5) {
            return false;
        }

        $minute = (int) $date->format('i');
        $hour = (int) $date->format('G');
        $dayOfMonth = (int) $date->format('j');
        $month = (int) $date->format('n');
        $dayOfWeek = (int) $date->format('w'); // 0 (Sun) to 6 (Sat)

        return $this->matchPart($parts[0], $minute, 0, 59)
            && $this->matchPart($parts[1], $hour, 0, 23)
            && $this->matchPart($parts[2], $dayOfMonth, 1, 31)
            && $this->matchPart($parts[3], $month, 1, 12)
            && $this->matchPart($parts[4], $dayOfWeek, 0, 6, true);
    }

    /**
     * Evaluate a single segment of the cron expression against a value.
     */
    private function matchPart(string $part, int $value, int $min, int $max, bool $isDayOfWeek = false): bool
    {
        if ($part === '*') {
            return true;
        }

        // Support Sunday as 7 as well
        if ($isDayOfWeek && $value === 0 && str_contains($part, '7')) {
            $value = 7;
        }

        // Support comma-separated items: "1,5,10"
        foreach (explode(',', $part) as $subPart) {
            $subPart = trim($subPart);

            // Step values: "*/5" or "10-30/5"
            if (str_contains($subPart, '/')) {
                [$range, $step] = explode('/', $subPart, 2);
                $step = (int) $step;
                if ($step <= 0) {
                    continue;
                }

                if ($range === '*') {
                    if (($value - $min) % $step === 0) {
                        return true;
                    }
                } elseif (str_contains($range, '-')) {
                    [$start, $end] = explode('-', $range, 2);
                    $start = (int) $start;
                    $end = (int) $end;
                    if ($value >= $start && $value <= $end && (($value - $start) % $step === 0)) {
                        return true;
                    }
                }

                continue;
            }

            // Range values: "1-5"
            if (str_contains($subPart, '-')) {
                [$start, $end] = explode('-', $subPart, 2);
                if ($value >= (int) $start && $value <= (int) $end) {
                    return true;
                }

                continue;
            }

            // Exact match
            if ((int) $subPart === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the next DateTime this cron expression will run after the given date.
     */
    public function getNextRunDate(?DateTimeInterface $date = null): DateTime
    {
        $current = $date ? (new DateTime)->setTimestamp($date->getTimestamp()) : new DateTime;
        $current->setTime((int) $current->format('H'), (int) $current->format('i'), 0);
        $current->modify('+1 minute');

        $parts = preg_split('/\s+/', $this->expression);

        for ($i = 0; $i < 100000; $i++) {
            if (count($parts) === 5) {
                $dayOfMonth = (int) $current->format('j');
                $month = (int) $current->format('n');
                $dayOfWeek = (int) $current->format('w');

                if (! $this->matchPart($parts[2], $dayOfMonth, 1, 31)
                    || ! $this->matchPart($parts[3], $month, 1, 12)
                    || ! $this->matchPart($parts[4], $dayOfWeek, 0, 6, true)) {
                    $current->setTime(0, 0, 0);
                    $current->modify('+1 day');

                    continue;
                }

                $hour = (int) $current->format('G');
                if (! $this->matchPart($parts[1], $hour, 0, 23)) {
                    $current->setTime($hour, 0, 0);
                    $current->modify('+1 hour');

                    continue;
                }
            }

            if ($this->isDue($current)) {
                return $current;
            }

            $current->modify('+1 minute');
        }

        return $current;
    }

    /**
     * Return human-friendly time remaining until the next run.
     * e.g. "due now", "in 4m", "in 2h 15m", "in 5d 2h"
     */
    public function getNextRunHuman(?DateTimeInterface $date = null): string
    {
        $now = $date ? (new DateTime)->setTimestamp($date->getTimestamp()) : new DateTime;

        if ($this->isDue($now)) {
            return 'due now';
        }

        $next = $this->getNextRunDate($now);
        $diff = $now->diff($next);

        $parts = [];
        if ($diff->days > 0) {
            $parts[] = "{$diff->days}d";
        }
        if ($diff->h > 0) {
            $parts[] = "{$diff->h}h";
        }
        if ($diff->i > 0) {
            $parts[] = "{$diff->i}m";
        }

        if (empty($parts)) {
            return 'in < 1m';
        }

        return 'in '.implode(' ', $parts);
    }

    /**
     * Convert expression into a human-readable summary.
     */
    public function toHumanString(): string
    {
        $parts = preg_split('/\s+/', $this->expression);
        if (count($parts) !== 5) {
            return $this->expression;
        }

        [$m, $h, $dom, $mon, $dow] = $parts;

        if ($this->expression === '* * * * *') {
            return 'Every minute';
        }
        if (preg_match('/^\*\/([0-9]+) \* \* \* \*$/', $this->expression, $match)) {
            return "Every {$match[1]} minutes";
        }
        if ($m === '0' && $h === '*') {
            return 'Every hour';
        }
        if (preg_match('/^([0-9]+) \* \* \* \*$/', $this->expression, $match)) {
            return "Every hour at minute {$match[1]}";
        }
        if ($dom === '*' && $mon === '*' && $dow === '*') {
            return sprintf('Every day at %02d:%02d', (int) $h, (int) $m);
        }
        if ($dom === '*' && $mon === '*' && is_numeric($dow)) {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $dayName = $days[(int) $dow] ?? "Day {$dow}";

            return sprintf('Weekly on %s at %02d:%02d', $dayName, (int) $h, (int) $m);
        }
        if (is_numeric($dom) && $mon === '*' && $dow === '*') {
            return sprintf('Monthly on day %d at %02d:%02d', (int) $dom, (int) $h, (int) $m);
        }

        return $this->expression;
    }
}
