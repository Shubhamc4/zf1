<?php

declare(strict_types=1);

namespace ZF1\Queue;

use DateInterval;
use DateTimeInterface;

final class PendingDispatch
{
    private string $jobClass;

    private string $queue = 'default';

    private int|DateInterval|DateTimeInterface $delay = 0;

    /** @var int|array<int>|null */
    private int|array|null $backoff = null;

    private ?int $tries = null;

    public function __construct(string $jobClass)
    {
        $this->jobClass = $jobClass;
    }

    /**
     * Specify the target queue channel.
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Specify a delay before execution.
     */
    public function delay(int|DateInterval|DateTimeInterface $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * Specify the maximum number of attempts for this job.
     */
    public function tries(int $tries): self
    {
        $this->tries = max(1, $tries);

        return $this;
    }

    /**
     * Alias for tries().
     */
    public function withTries(int $tries): self
    {
        return $this->tries($tries);
    }

    /**
     * Specify the retry backoff duration(s) in seconds.
     * Supports a single int (10), an array ([10, 20, 30]), or multiple int arguments (10, 20, 30).
     *
     * @param  int|array<int>  ...$backoff
     */
    public function backoff(int|array ...$backoff): self
    {
        if (count($backoff) === 1 && (is_array($backoff[0]) || is_int($backoff[0]))) {
            $this->backoff = $backoff[0];
        } else {
            $this->backoff = array_values(array_map('intval', $backoff));
        }

        return $this;
    }

    /**
     * Alias for backoff().
     *
     * @param  int|array<int>  ...$backoff
     */
    public function withBackoff(int|array ...$backoff): self
    {
        return $this->backoff(...$backoff);
    }

    /**
     * Dispatch the job.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(array $payload = []): int
    {
        if (! empty($this->delay)) {
            return Queue::later($this->delay, $this->jobClass, $payload, $this->queue, null, $this->backoff, $this->tries);
        }

        return Queue::push($this->jobClass, $payload, $this->queue, 0, null, $this->backoff, $this->tries);
    }
}
