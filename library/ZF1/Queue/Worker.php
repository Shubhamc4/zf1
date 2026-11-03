<?php

declare(strict_types=1);

namespace ZF1\Queue;

use ArgumentCountError;
use ReflectionMethod;
use Throwable;
use Zend_Log;
use ZF1\Queue\Contracts\JobInterface;

final class Worker
{
    private static ?bool $skipFileLog = null;

    private static bool $verbose = false;

    public static function isVerbose(): bool
    {
        return self::$verbose;
    }

    /**
     * Run the background queue worker loop.
     *
     * @param  array<string, mixed>  $options  CLI options (queue, sleep, max-jobs, memory-limit)
     */
    public static function run(array $options = []): void
    {
        if (isset($options['no-file-log']) || isset($options['stdout-only'])) {
            self::$skipFileLog = true;
        } elseif (isset($options['file-log'])) {
            self::$skipFileLog = false;
        }
        self::$verbose = ! empty($options['verbose']) || ! empty($options['v']);
        $queueName = (string) ($options['queue'] ?? 'default');
        $sleepSeconds = max(1, (int) ($options['sleep'] ?? 2));
        $maxJobs = max(0, (int) ($options['max-jobs'] ?? 0));
        $memoryLimitMb = max(16, (int) ($options['memory-limit'] ?? 128));
        $stopWhenEmpty = ! empty($options['stop-when-empty']);
        $defaultTries = max(1, (int) ($options['tries'] ?? 3));
        $defaultBackoff = $options['backoff'] ?? null;

        $shouldQuit = false;

        if (extension_loaded('pcntl')) {
            $signalHandler = function (int $signal) use (&$shouldQuit) {
                self::log("Received signal {$signal}. Shutting down worker gracefully...");
                $shouldQuit = true;
            };

            pcntl_signal(SIGTERM, $signalHandler);
            pcntl_signal(SIGINT, $signalHandler);
            pcntl_signal(SIGHUP, $signalHandler);
        }

        self::log(sprintf(
            "%s queue worker started for queue: '%s' (sleep: %ds, max-jobs: %d, memory-limit: %dMB)",
            env('APP_NAME', 'ZF1'), $queueName, $sleepSeconds, $maxJobs, $memoryLimitMb
        ));

        $processedJobs = 0;

        while (! $shouldQuit) {
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }

            if ($shouldQuit) {
                break;
            }

            // Check memory limit
            $memoryUsedMb = memory_get_usage(true) / 1024 / 1024;
            if ($memoryUsedMb >= $memoryLimitMb) {
                self::log(sprintf(
                    'Memory limit exceeded (%0.2fMB >= %dMB). Restarting worker.',
                    $memoryUsedMb, $memoryLimitMb
                ));
                break;
            }

            try {
                $job = Queue::pop($queueName, 60);

                if ($job === null) {
                    if ($stopWhenEmpty) {
                        self::log('Queue is empty. Exiting worker cleanly.');
                        break;
                    }

                    sleep($sleepSeconds);

                    continue;
                }

                $processedJobs++;
                $jobId = (int) $job['id'];
                $rawBody = (string) $job['payload'];
                $data = json_decode($rawBody, true);

                if (! is_array($data) || empty($data['job'])) {
                    self::log(sprintf('[Job #%d] Invalid payload format. Discarding job.', $jobId), true);
                    Queue::delete($jobId);

                    continue;
                }

                $jobIdentifier = $data['job'];
                $payload = $data['payload'] ?? [];

                // Resolve job class and clean short name
                $jobClass = Queue::resolveJobClass($jobIdentifier) ?? $jobIdentifier;
                $shortClass = Queue::getJobShortName($jobClass);

                $startTime = microtime(true);
                $success = false;
                $failureException = null;
                $instance = null;
                Job::$lastJobError = null;

                $maxTries = isset($data['tries']) && is_numeric($data['tries']) ? max(1, (int) $data['tries']) : $defaultTries;

                if (class_exists($jobClass)) {
                    $instance = new $jobClass;

                    if ($instance instanceof Job) {
                        if (! isset($data['tries'])) {
                            $maxTries = method_exists($instance, 'tries') ? (int) $instance->tries() : (int) ($instance->tries ?? $defaultTries);
                        }

                        if ($instance->isUnique()) {
                            Queue::coalesceDuplicates($jobClass, $payload, $queueName, $jobId);
                        }
                    }
                }

                $attempts = (int) $job['attempts'];

                // 1. Log when the job started
                self::log(sprintf(
                    '[Job #%d] Processing: %s (attempt #%d/%d)',
                    $jobId, $shortClass, $attempts, $maxTries
                ));

                if ($instance !== null) {
                    try {
                        if ($instance instanceof JobInterface || is_callable([$instance, 'handle'])) {
                            $result = $instance->handle($payload);
                            $success = ($result !== false);
                        } else {
                            $failureException = "Handler {$shortClass} does not implement JobInterface or handle().";
                        }
                    } catch (Throwable $jobEx) {
                        $failureException = $jobEx;
                        $success = false;
                    }
                } else {
                    $failureException = "Job class '{$jobClass}' not found.";
                }

                $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

                if ($success) {
                    Queue::delete($jobId);
                    self::log(sprintf(
                        '[Job #%d] Completed successfully (%sms).',
                        $jobId, $elapsedMs
                    ));
                } else {
                    $shouldFailPermanently = false;
                    if (isset($instance) && is_object($instance)) {
                        if (method_exists($instance, 'hasFailedImmediately') && $instance->hasFailedImmediately()) {
                            $shouldFailPermanently = true;
                            $immediateReason = $instance->getImmediateFailureReason();
                            if ($immediateReason !== null) {
                                $failureException = $immediateReason;
                            }
                        } elseif (method_exists($instance, 'shouldntRetry')) {
                            if ($failureException !== null && $instance->shouldntRetry($failureException)) {
                                $shouldFailPermanently = true;
                            } elseif (Job::$lastJobError !== null && $instance->shouldntRetry(Job::$lastJobError)) {
                                $shouldFailPermanently = true;
                            }
                        }
                    }

                    if (! $shouldFailPermanently) {
                        if ($failureException !== null && Job::isPermanentError($failureException)) {
                            $shouldFailPermanently = true;
                        } elseif (Job::$lastJobError !== null && Job::isPermanentError(Job::$lastJobError)) {
                            $shouldFailPermanently = true;
                        }
                    }

                    $attempts = (int) $job['attempts'];

                    $rawReason = null;
                    if ($failureException instanceof Throwable) {
                        $rawReason = $failureException->getMessage();
                    } elseif (is_string($failureException) && $failureException !== '') {
                        $rawReason = $failureException;
                    } elseif (Job::$lastJobError !== null && Job::$lastJobError !== '') {
                        $rawReason = Job::$lastJobError;
                    } else {
                        $rawReason = 'Job returned false or handler failed.';
                    }
                    $reasonSummary = trim(strtok($rawReason, "\r\n"));

                    if ($attempts >= $maxTries || $shouldFailPermanently) {
                        $failReason = $failureException ?? $rawReason;
                        Queue::fail($jobId, $job, $failReason);
                        self::log(sprintf(
                            '[Job #%d] Failed (%sms, attempt %d/%d). Moved to failed jobs: %s',
                            $jobId, $elapsedMs, $attempts, $maxTries, $reasonSummary
                        ), true);
                    } else {
                        $jobBackoff = null;
                        if (isset($instance) && is_object($instance)) {
                            if (method_exists($instance, 'backoff')) {
                                try {
                                    $refMethod = new ReflectionMethod($instance, 'backoff');
                                    $paramsCount = $refMethod->getNumberOfParameters();
                                    if ($paramsCount >= 2) {
                                        $jobBackoff = $instance->backoff($attempts, $failureException instanceof Throwable ? $failureException : null);
                                    } elseif ($paramsCount === 1) {
                                        $jobBackoff = $instance->backoff($attempts);
                                    } else {
                                        $jobBackoff = $instance->backoff();
                                    }
                                } catch (ArgumentCountError) {
                                    try {
                                        $jobBackoff = $instance->backoff($attempts);
                                    } catch (Throwable) {
                                        try {
                                            $jobBackoff = $instance->backoff();
                                        } catch (Throwable) {
                                            $jobBackoff = null;
                                        }
                                    }
                                } catch (Throwable) {
                                    $jobBackoff = null;
                                }
                            }

                            if ($jobBackoff === null && isset($instance->backoff)) {
                                $jobBackoff = $instance->backoff;
                            }
                        }

                        if ($jobBackoff === null && isset($data['backoff'])) {
                            $jobBackoff = $data['backoff'];
                        }

                        if ($jobBackoff === null && $defaultBackoff !== null) {
                            $jobBackoff = $defaultBackoff;
                        }

                        $delay = self::calculateBackoff($jobBackoff, $attempts, min(30 * $attempts, 300));
                        Queue::release($jobId, $delay);
                        self::log(sprintf(
                            '[Job #%d] Failed (%sms, attempt %d/%d). Retrying in %ds: %s',
                            $jobId, $elapsedMs, $attempts, $maxTries, $delay, $reasonSummary
                        ), true);
                    }
                }

                if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                    self::log(sprintf('Reached max-jobs limit (%d). Exiting cleanly.', $maxJobs));
                    break;
                }
            } catch (Throwable $e) {
                self::log(sprintf(
                    'Queue Worker Exception: %s in %s:%d',
                    $e->getMessage(), $e->getFile(), $e->getLine()
                ), true);
                sleep($sleepSeconds);
            }
        }
    }

    /**
     * Log a message to CLI and storage/logs/queue.log.
     */
    public static function log(string $message, bool $isError = false): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = sprintf("[%s] %s\n", $timestamp, $message);

        if (php_sapi_name() === 'cli') {
            if ($isError && defined('STDERR')) {
                fwrite(STDERR, $formatted);
            } else {
                echo $formatted;
            }
        }

        if (self::shouldSkipFileLog()) {
            return;
        }

        try {
            \ZF1\Foundation\Bootstrap::queueLog($message, $isError ? Zend_Log::ERR : Zend_Log::INFO);
        } catch (Throwable $e) {
            // Never allow log file permission issues to crash the queue worker process
            if (defined('STDERR')) {
                fwrite(STDERR, sprintf("[%s] [WARNING] Could not write to queue.log: %s\n", $timestamp, $e->getMessage()));
            }
        }
    }

    /**
     * Determine whether direct file logging should be skipped.
     */
    public static function shouldSkipFileLog(): bool
    {
        if (self::$skipFileLog !== null) {
            return self::$skipFileLog;
        }

        if (env('QUEUE_LOG_STDOUT_ONLY', false) || env('QUEUE_WORKER_NO_FILE_LOG', false)) {
            return true;
        }

        if (
            getenv('SUPERVISOR_PROCESS_NAME') !== false
            || isset($_SERVER['SUPERVISOR_PROCESS_NAME'])
            || isset($_ENV['SUPERVISOR_PROCESS_NAME'])
            || getenv('SUPERVISOR_SERVER_URL') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * Calculate backoff delay in seconds for a given retry attempt.
     *
     * Supports:
     * - A single integer: e.g. 10 (every retry waits 10s).
     * - An array of integers: e.g. [10, 20, 30] (1st retry waits 10s, 2nd waits 20s, 3rd and subsequent wait 30s).
     * - A comma-separated string: e.g. "10,20,30" (CLI option).
     *
     * @param  int|array<int>|string|null  $backoff
     * @param  int  $attempts  Current attempt number that just failed (1, 2, 3...)
     * @param  int  $default  Default fallback delay if no backoff is configured
     * @return int Delay in seconds
     */
    public static function calculateBackoff(int|array|string|null $backoff, int $attempts, int $default = 0): int
    {
        if ($backoff === null || $backoff === '') {
            return $default;
        }

        if (is_string($backoff)) {
            $parts = array_map('trim', explode(',', $backoff));
            $backoff = array_map('intval', $parts);
        }

        if (is_array($backoff)) {
            if (empty($backoff)) {
                return $default;
            }

            $values = array_values(array_map('intval', $backoff));
            $index = max(0, $attempts - 1);

            if (isset($values[$index])) {
                return max(0, $values[$index]);
            }

            return max(0, end($values));
        }

        return max(0, (int) $backoff);
    }
}
