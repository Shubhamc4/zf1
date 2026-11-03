<?php

declare(strict_types=1);

namespace ZF1\Queue;

use BadMethodCallException;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;
use LengthException;
use OutOfRangeException;
use Throwable;
use TypeError;
use UnexpectedValueException;
use Zend_Log;
use Zend_Log_Writer_Stream;
use ZF1\Queue\Contracts\JobInterface;

abstract class Job implements JobInterface
{
    /**
     * Maximum number of attempts before moving job to failed jobs table.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Can be an integer (e.g. 10) or an array of integers (e.g. [10, 20, 30]).
     *
     * @var int|array<int>|null
     */
    public $backoff = null;

    /**
     * Whether this job coalesces duplicate pending jobs with identical payloads.
     * Enabled (true) by default across all jobs.
     */
    public bool $unique = true;

    /**
     * List of additional exception classes that should not be retried.
     * Common unrecoverable errors (InvalidArgumentException, TypeError, 4xx, 500 errors)
     * are automatically detected and stopped without needing to be listed here.
     *
     * @var array<class-string<Throwable>>
     */
    public array $dontRetry = [];

    /**
     * Whether this job has been explicitly marked to fail immediately without retry.
     */
    protected bool $shouldFailImmediately = false;

    /**
     * The reason or exception associated with immediate failure.
     */
    protected Throwable|string|null $immediateFailureReason = null;

    /**
     * Last error recorded by a job during execution.
     */
    public static ?string $lastJobError = null;

    /**
     * Number of times the job may be attempted. Default is 3.
     */
    public function tries(): int
    {
        return $this->tries;
    }

    /**
     * Alias for tries().
     */
    public function getTries(): int
    {
        return $this->tries();
    }

    /**
     * Calculate or get the number of seconds to wait before retrying the job.
     *
     * @return int|array<int>|null
     */
    public function backoff(?int $attempts = null, ?Throwable $exception = null): int|array|null
    {
        return $this->backoff;
    }

    /**
     * Alias for backoff().
     *
     * @return int|array<int>|null
     */
    public function getBackoff(?int $attempts = null, ?Throwable $exception = null): int|array|null
    {
        return $this->backoff($attempts, $exception);
    }

    /**
     * Mark the job as failed immediately and abort all future retries.
     */
    public function fail(Throwable|string|null $reason = null): void
    {
        $this->shouldFailImmediately = true;
        $this->immediateFailureReason = $reason;
    }

    /**
     * Check whether the job was marked to fail immediately without retry.
     */
    public function hasFailedImmediately(): bool
    {
        return $this->shouldFailImmediately;
    }

    /**
     * Get the reason why the job failed immediately.
     */
    public function getImmediateFailureReason(): Throwable|string|null
    {
        return $this->immediateFailureReason;
    }

    /**
     * Determine whether the given exception, error message, or job state should not be retried.
     */
    public function shouldntRetry(Throwable|string|null $reason = null): bool
    {
        if ($this->shouldFailImmediately) {
            return true;
        }

        if ($reason === null) {
            return false;
        }

        // 1. Check custom classes defined in child job's $dontRetry
        if ($reason instanceof Throwable && ! empty($this->dontRetry)) {
            foreach ($this->dontRetry as $dontRetryClass) {
                if ($reason instanceof $dontRetryClass) {
                    return true;
                }
            }
        }

        // 2. Built-in universal permanent error check (400s, 500s, InvalidArgument, TypeError, SMTP, etc.)
        return static::isPermanentError($reason);
    }

    /**
     * Determine whether an error is permanent and should fail immediately without retry.
     * Automatically handles 400 errors, 500 errors, invalid arguments, type errors, and unrecoverable SMTP errors.
     */
    public static function isPermanentError(Throwable|string|null $reason): bool
    {
        if ($reason === null) {
            return false;
        }

        if ($reason instanceof Throwable) {
            // Check default unrecoverable PHP exceptions
            $unrecoverableClasses = [
                InvalidArgumentException::class,
                TypeError::class,
                BadMethodCallException::class,
                DomainException::class,
                LengthException::class,
                OutOfRangeException::class,
                UnexpectedValueException::class,
            ];

            foreach ($unrecoverableClasses as $cls) {
                if ($reason instanceof $cls) {
                    return true;
                }
            }

            // Check HTTP / Exception status code
            $code = (int) $reason->getCode();
            // 4xx Client Errors (400 to 499, excluding 429 Too Many Requests which is retriable)
            if ($code >= 400 && $code < 500 && $code !== 429) {
                return true;
            }
            // 500 Server Errors & permanent SMTP response codes
            if ($code === 500 || $code === 501 || $code === 505 || ($code >= 534 && $code <= 554)) {
                return true;
            }

            // Guzzle / PSR-7 response status code
            if (method_exists($reason, 'hasResponse') && $reason->hasResponse() && method_exists($reason, 'getResponse')) {
                $response = $reason->getResponse();
                if (is_object($response) && method_exists($response, 'getStatusCode')) {
                    $statusCode = (int) $response->getStatusCode();
                    if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                        return true;
                    }
                    if ($statusCode === 500 || $statusCode === 501 || $statusCode === 505) {
                        return true;
                    }
                }
            }

            return static::matchesPermanentPattern($reason->getMessage());
        }

        if (is_string($reason)) {
            return static::matchesPermanentPattern($reason);
        }

        return false;
    }

    /**
     * Match error message text against common permanent failure patterns (400, 500, invalid argument, auth, SMTP).
     */
    public static function matchesPermanentPattern(string $message): bool
    {
        if (trim($message) === '') {
            return false;
        }

        $patterns = [
            // 4xx Client Errors (400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 422 Unprocessable, etc.)
            '/\b(400|401|402|403|404|405|406|409|410|422)\b/i',
            '/\b4\d{2}\s+(?:client\s+)?error\b/i',
            '/bad request|unauthorized|forbidden|not found|unprocessable|method not allowed/i',

            // 500 Internal Server Errors & permanent server errors
            '/\b500\s*(?:internal\s*)?server\s*error\b/i',
            '/\b(?:http|code|status|error)[:\s]+500\b/i',
            '/\b500\b/i',
            '/\b501\b|\b505\b/i',

            // SMTP 5xx permanent bounce / auth errors
            '/\b5\.7\.\d\b/i',
            '/\b(534|535|550|554)\b/i',
            '/username and password not accepted|authentication failed|invalid credentials|password not accepted/i',
            '/user unknown|no such user|address rejected|recipient.*reject|mailbox unavailable|invalid address/i',

            // Invalid argument / payload / typing / validation errors
            '/invalid\s+(?:argument|payload|input|parameter|data|value|format|email)/i',
            '/missing\s+(?:required|template|parameter|argument|field|key|data|payload)/i',
            '/validation\s+(?:failed|error)/i',
            '/syntax error/i',
            '/call to undefined (?:method|function)/i',
            '/uncaught typeerror/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if job is unique.
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * Generate a deterministic unique signature for this job and payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function uniqueKey(array $payload): string
    {
        return static::generateUniqueKey(static::class, $payload);
    }

    /**
     * Generate a deterministic unique signature for any job class and payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function generateUniqueKey(string $jobClass, array $payload): string
    {
        $normalizedPayload = static::normalizePayload($payload);

        return md5($jobClass.':'.json_encode($normalizedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively sort arrays by keys for consistent JSON hashing.
     */
    public static function normalizePayload(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        ksort($data);
        foreach ($data as $key => $value) {
            $data[$key] = static::normalizePayload($value);
        }

        return $data;
    }

    /**
     * Dispatch the job onto the queue.
     *
     * @param  array<string, mixed>  $payload  Job arguments
     * @param  string  $queue  Queue channel name
     * @param  int  $delay  Delay in seconds before the job becomes available
     * @return int Inserted job ID
     */
    public static function queue(array $payload = [], string $queue = 'default', int $delay = 0): int
    {
        return Queue::push(static::class, $payload, $queue, $delay);
    }

    /**
     * Dispatch the job onto the queue.
     *
     * @param  array<string, mixed>  $payload  Job arguments
     * @param  string  $queue  Queue channel name
     * @param  int  $delay  Delay in seconds before the job becomes available
     * @return int Inserted job ID
     */
    public static function dispatch(array $payload = [], string $queue = 'default', int $delay = 0): int
    {
        return static::queue($payload, $queue, $delay);
    }

    /**
     * Dispatch the job after a delay.
     *
     * @param  int|DateInterval|DateTimeInterface  $delay  Delay in seconds, DateInterval, or future DateTime
     * @param  array<string, mixed>  $payload  Job arguments
     * @param  string  $queue  Queue channel name
     * @return int Inserted job ID
     */
    public static function later(
        int|DateInterval|DateTimeInterface $delay,
        array $payload = [],
        string $queue = 'default'
    ): int {
        return Queue::later($delay, static::class, $payload, $queue);
    }

    /**
     * Execute the job synchronously immediately.
     *
     * @param  array<string, mixed>  $payload  Job arguments
     * @return bool True if successful, false on failure
     */
    public static function dispatchSync(array $payload = []): bool
    {
        $instance = new static;

        return $instance->handle($payload) !== false;
    }

    /**
     * Start a fluent dispatch configuration with a custom queue.
     */
    public static function onQueue(string $queue): PendingDispatch
    {
        return (new PendingDispatch(static::class))->onQueue($queue);
    }

    /**
     * Start a fluent dispatch configuration with a delay.
     */
    public static function delay(int|DateInterval|DateTimeInterface $delay): PendingDispatch
    {
        return (new PendingDispatch(static::class))->delay($delay);
    }

    /**
     * Start a fluent dispatch configuration with maximum attempts/tries.
     */
    public static function withTries(int $tries): PendingDispatch
    {
        return (new PendingDispatch(static::class))->tries($tries);
    }

    /**
     * Start a fluent dispatch configuration with a retry backoff duration(s).
     *
     * @param  int|array<int>  $backoff
     */
    public static function withBackoff(int|array $backoff): PendingDispatch
    {
        return (new PendingDispatch(static::class))->backoff($backoff);
    }

    /**
     * Log an informational message.
     *
     * @param  string|array<string, mixed>  $message
     * @param  array<string, mixed>  $context
     */
    public static function info(string|array $message, array $context = []): void
    {
        static::writeLog('INFO', $message, $context, Zend_Log::INFO);
    }

    /**
     * Log a debug message.
     *
     * @param  string|array<string, mixed>  $message
     * @param  array<string, mixed>  $context
     */
    public static function debug(string|array $message, array $context = []): void
    {
        static::writeLog('DEBUG', $message, $context, Zend_Log::DEBUG);
    }

    /**
     * Log a warning message.
     *
     * @param  string|array<string, mixed>  $message
     * @param  array<string, mixed>  $context
     */
    public static function warning(string|array $message, array $context = []): void
    {
        static::writeLog('WARN', $message, $context, Zend_Log::WARN);
    }

    /**
     * Alias for warning.
     *
     * @param  string|array<string, mixed>  $message
     * @param  array<string, mixed>  $context
     */
    public static function warn(string|array $message, array $context = []): void
    {
        static::warning($message, $context);
    }

    /**
     * Log an error message.
     *
     * @param  string|array<string, mixed>  $message
     * @param  array<string, mixed>  $context
     */
    public static function error(string|array $message, array $context = []): void
    {
        static::writeLog('ERROR', $message, $context, Zend_Log::ERR);
    }

    /**
     * Write formatted log to CLI console and Zend Framework logger.
     *
     * @param  string|array<string, mixed>  $message
     * @param  array<string, mixed>  $context
     */
    protected static function writeLog(
        string $level,
        string|array $message,
        array $context,
        int $zendLogLevel
    ): void {
        $className = static::getJobShortName();
        $timestamp = (new DateTimeImmutable)->format('Y-m-d H:i:s');

        $messageString = is_array($message)
            ? json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $message;

        if (! empty($context)) {
            $contextString = static::formatContext($context);
            $messageString .= ' '.$contextString;
        }

        if ($zendLogLevel <= Zend_Log::ERR) {
            static::$lastJobError = $messageString;
        }

        $isVerbose = (class_exists(Worker::class, false) && Worker::isVerbose());
        if ($isVerbose) {
            $formatted = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $level, $className, $messageString);
            if ($zendLogLevel <= Zend_Log::WARN) {
                if (defined('STDERR')) {
                    fwrite(STDERR, $formatted);
                } else {
                    file_put_contents('php://stderr', $formatted);
                }
            } else {
                if (defined('STDOUT')) {
                    fwrite(STDOUT, $formatted);
                } else {
                    file_put_contents('php://stdout', $formatted);
                }
            }
        }

        try {
            $logFile = ensure_directory_exists(storage_path('logs/jobs.log'));

            $logger = new Zend_Log;
            $logger->addWriter(new Zend_Log_Writer_Stream($logFile, 'a'));
            $logger->log("[{$className}] [{$level}] {$messageString}", $zendLogLevel);
        } catch (Throwable) {
            // Silently fallback if jobs.log cannot be written
        }
    }

    /**
     * Format context array, handling Exceptions cleanly.
     *
     * @param  array<string, mixed>  $context
     */
    private static function formatContext(array $context): string
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $sanitized[$key] = [
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'file' => $value->getFile().':'.$value->getLine(),
                ];
            } else {
                $sanitized[$key] = $value;
            }
        }

        return json_encode($sanitized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Get class base name without namespace.
     */
    private static function getJobShortName(): string
    {
        $class = static::class;
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
