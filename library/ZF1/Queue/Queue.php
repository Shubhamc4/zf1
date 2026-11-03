<?php

declare(strict_types=1);

namespace ZF1\Queue;

use DateInterval;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table;
use ZF1\Logging\DiscordNotifier;
use ZF1\Queue\Contracts\JobInterface;

final class Queue
{
    /** @var string Database table name for jobs */
    private const TABLE_NAME = 'zend_jobs';

    /** @var string Database table name for failed jobs */
    private const TABLE_FAILED_JOBS = 'zend_failed_jobs';

    /**
     * Get the configured queue driver ('sync' or 'database'). Default is 'sync'.
     */
    public static function getConnection(): string
    {
        return strtolower((string) env('QUEUE_CONNECTION', 'sync'));
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  string  $job  Full class name or short name inside App\Jobs\
     * @param  array<string, mixed>  $payload  Job arguments
     * @param  string  $queue  Name of queue channel (default: 'default')
     * @param  int  $delay  Delay in seconds before the job becomes available
     * @return int Inserted job ID
     */
    public static function push(
        string $job,
        array $payload = [],
        string $queue = 'default',
        int $delay = 0,
        ?Zend_Db_Adapter_Abstract $db = null,
        int|array|null $backoff = null,
        ?int $tries = null
    ): int {
        if (self::getConnection() === 'sync') {
            return self::executeSync($job, $payload);
        }

        $adapter = $db ?? self::getAdapter();

        // Check if job is unique and already pending in this queue
        $resolvedClass = self::resolveJobClass($job);
        if ($resolvedClass !== null && is_subclass_of($resolvedClass, Job::class)) {
            $jobInstance = new $resolvedClass;
            if ($jobInstance->isUnique()) {
                $existingId = self::findDuplicatePendingJob($resolvedClass, $payload, $queue, $adapter);
                if ($existingId !== null) {
                    return $existingId;
                }
            }
        }

        $dataArray = [
            'job' => $job,
            'payload' => $payload,
        ];

        if ($backoff !== null) {
            $dataArray['backoff'] = $backoff;
        }

        if ($tries !== null) {
            $dataArray['tries'] = max(1, $tries);
        }

        $data = json_encode($dataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($data === false) {
            throw new InvalidArgumentException('Failed to JSON encode queue payload.');
        }

        $now = time();
        $availableAt = $now + max(0, $delay);

        $adapter->insert(self::TABLE_NAME, [
            'queue' => $queue,
            'payload' => $data,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => $now,
        ]);

        return (int) $adapter->lastInsertId();
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  int|DateInterval|DateTimeInterface  $delay  Delay in seconds, DateInterval, or future DateTime
     * @param  string  $job  Full class name or short name inside App\Jobs\
     * @param  array<string, mixed>  $payload  Job arguments
     * @param  string  $queue  Name of queue channel (default: 'default')
     * @return int Inserted job ID
     */
    public static function later(
        int|DateInterval|DateTimeInterface $delay,
        string $job,
        array $payload = [],
        string $queue = 'default',
        ?Zend_Db_Adapter_Abstract $db = null,
        int|array|null $backoff = null,
        ?int $tries = null
    ): int {
        if (self::getConnection() === 'sync') {
            return self::executeSync($job, $payload);
        }

        $seconds = self::parseDelay($delay);

        return self::push($job, $payload, $queue, $seconds, $db, $backoff, $tries);
    }

    /**
     * Reserve and fetch the next available job from the queue.
     *
     * @param  string  $queue  Name of queue channel (default: 'default')
     * @param  int  $timeout  Visibility timeout in seconds before unreserved jobs are retried
     * @return array<string, mixed>|null Job record or null if queue is empty
     */
    public static function pop(
        string $queue = 'default',
        int $timeout = 60,
        ?Zend_Db_Adapter_Abstract $db = null
    ): ?array {
        $adapter = $db ?? self::getAdapter();
        $now = time();
        $expiredReservation = $now - max(1, $timeout);

        $adapter->beginTransaction();

        try {
            // Find next available job with row locking
            $sql = sprintf(
                'SELECT * FROM %s WHERE queue = ? AND (reserved_at IS NULL OR reserved_at <= ?) AND available_at <= ? ORDER BY id ASC LIMIT 1 FOR UPDATE',
                self::TABLE_NAME
            );

            $job = $adapter->fetchRow($sql, [$queue, $expiredReservation, $now]);

            if (! $job) {
                $adapter->commit();

                return null;
            }

            $newAttempts = (int) $job['attempts'] + 1;

            // Reserve job and increment attempts
            $adapter->update(
                self::TABLE_NAME,
                [
                    'reserved_at' => $now,
                    'attempts' => $newAttempts,
                ],
                ['id = ?' => (int) $job['id']]
            );

            $adapter->commit();

            $job['attempts'] = $newAttempts;

            return $job;
        } catch (Throwable $e) {
            $adapter->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a completed job from the queue.
     */
    public static function delete(int $jobId, ?Zend_Db_Adapter_Abstract $db = null): bool
    {
        $adapter = $db ?? self::getAdapter();

        return (bool) $adapter->delete(self::TABLE_NAME, ['id = ?' => $jobId]);
    }

    /**
     * Release a failed or uncompleted job back to the queue with an optional delay.
     */
    public static function release(int $jobId, int $delay = 30, ?Zend_Db_Adapter_Abstract $db = null): bool
    {
        $adapter = $db ?? self::getAdapter();
        $availableAt = time() + max(0, $delay);

        return (bool) $adapter->update(
            self::TABLE_NAME,
            [
                'reserved_at' => null,
                'available_at' => $availableAt,
            ],
            ['id = ?' => $jobId]
        );
    }

    /**
     * Get the count of pending (available) jobs in the queue.
     */
    public static function count(
        string $queue = 'default',
        int $timeout = 60,
        ?Zend_Db_Adapter_Abstract $db = null
    ): int {
        $adapter = $db ?? self::getAdapter();
        $now = time();
        $expiredReservation = $now - max(1, $timeout);

        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE queue = ? AND (reserved_at IS NULL OR reserved_at <= ?) AND available_at <= ?',
            self::TABLE_NAME
        );

        return (int) $adapter->fetchOne($sql, [$queue, $expiredReservation, $now]);
    }

    /**
     * Delete unreserved duplicate pending jobs with identical payload from the queue.
     *
     * @return array<int> List of deleted duplicate job IDs
     */
    public static function coalesceDuplicates(
        string $jobClass,
        array $payload,
        string $queue = 'default',
        ?int $excludeId = null,
        ?Zend_Db_Adapter_Abstract $db = null
    ): array {
        $adapter = $db ?? self::getAdapter();
        $sql = sprintf('SELECT id, payload FROM %s WHERE queue = ? AND reserved_at IS NULL', self::TABLE_NAME);
        $rows = $adapter->fetchAll($sql, [$queue]);

        $normalizedTargetPayload = Job::normalizePayload($payload);
        $shortTargetClass = self::getJobShortName($jobClass);
        $duplicateIds = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($excludeId !== null && $id === $excludeId) {
                continue;
            }

            $data = json_decode((string) $row['payload'], true);
            if (! is_array($data) || empty($data['job'])) {
                continue;
            }

            $rowClass = (string) $data['job'];
            if ($rowClass !== $jobClass && self::getJobShortName($rowClass) !== $shortTargetClass) {
                continue;
            }

            $rowPayload = Job::normalizePayload($data['payload'] ?? []);
            if ($rowPayload === $normalizedTargetPayload) {
                $duplicateIds[] = $id;
            }
        }

        if (! empty($duplicateIds)) {
            $adapter->delete(self::TABLE_NAME, [
                'id IN (?)' => $duplicateIds,
                'reserved_at IS NULL',
            ]);
        }

        return $duplicateIds;
    }

    /**
     * Find an existing unreserved pending job in the queue with matching job and payload.
     */
    public static function findDuplicatePendingJob(
        string $jobClass,
        array $payload,
        string $queue = 'default',
        ?Zend_Db_Adapter_Abstract $db = null
    ): ?int {
        $adapter = $db ?? self::getAdapter();
        $sql = sprintf('SELECT id, payload FROM %s WHERE queue = ? AND reserved_at IS NULL', self::TABLE_NAME);
        $rows = $adapter->fetchAll($sql, [$queue]);

        $normalizedTargetPayload = Job::normalizePayload($payload);
        $shortTargetClass = self::getJobShortName($jobClass);

        foreach ($rows as $row) {
            $data = json_decode((string) $row['payload'], true);
            if (! is_array($data) || empty($data['job'])) {
                continue;
            }

            $rowClass = (string) $data['job'];
            if ($rowClass !== $jobClass && self::getJobShortName($rowClass) !== $shortTargetClass) {
                continue;
            }

            $rowPayload = Job::normalizePayload($data['payload'] ?? []);
            if ($rowPayload === $normalizedTargetPayload) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    /**
     * Record a failed job in the zend_failed_jobs table and remove from active queue.
     */
    public static function fail(
        int $jobId,
        array $job,
        Throwable|string $exception,
        ?Zend_Db_Adapter_Abstract $db = null
    ): int {
        $adapter = $db ?? self::getAdapter();
        $exceptionString = $exception instanceof Throwable
            ? (string) $exception
            : (string) $exception;

        $adapter->insert(self::TABLE_FAILED_JOBS, [
            'connection' => 'database',
            'queue' => $job['queue'] ?? 'default',
            'payload' => is_string($job['payload']) ? $job['payload'] : json_encode($job['payload']),
            'exception' => $exceptionString,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);

        $failedId = (int) $adapter->lastInsertId();

        $adapter->delete(self::TABLE_NAME, ['id = ?' => $jobId]);

        // Send Discord notification if configured
        $jobData = is_string($job['payload']) ? json_decode($job['payload'], true) : $job['payload'];
        $jobClass = $jobData['job'] ?? 'Unknown Job';
        $shortClass = self::getJobShortName($jobClass);
        $firstLineEx = strtok($exceptionString, "\n") ?: 'Job returned false or failed.';

        DiscordNotifier::error(
            "🚨 Job Failed: {$shortClass}",
            "Job #{$jobId} exceeded max attempts and moved to the dead-letter queue.",
            [
                'Job ID' => "#{$jobId}",
                'Job Class' => $jobClass,
                'Queue' => $job['queue'] ?? 'default',
                'Attempts' => $job['attempts'] ?? 'Max',
                'Error' => strlen($firstLineEx) > 100 ? substr($firstLineEx, 0, 97).'...' : $firstLineEx,
            ]
        );

        return $failedId;
    }

    /**
     * Get paginated failed jobs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getFailed(
        int $limit = 50,
        int $offset = 0,
        ?Zend_Db_Adapter_Abstract $db = null
    ): array {
        $adapter = $db ?? self::getAdapter();
        $sql = sprintf(
            'SELECT * FROM %s ORDER BY failed_at DESC LIMIT %d OFFSET %d',
            self::TABLE_FAILED_JOBS,
            $limit,
            $offset
        );

        return $adapter->fetchAll($sql);
    }

    /**
     * Find a failed job by ID.
     *
     * @return array<string, mixed>|null
     */
    public static function findFailed(int $id, ?Zend_Db_Adapter_Abstract $db = null): ?array
    {
        $adapter = $db ?? self::getAdapter();
        $sql = sprintf('SELECT * FROM %s WHERE id = ?', self::TABLE_FAILED_JOBS);
        $row = $adapter->fetchRow($sql, [$id]);

        return $row ?: null;
    }

    /**
     * Retry failed job(s) by moving them back into the active jobs table.
     *
     * @param  int|string  $idOrAll  Specific failed ID or 'all'
     * @return int Number of retried jobs
     */
    public static function retryFailed(
        int|string $idOrAll = 'all',
        ?Zend_Db_Adapter_Abstract $db = null
    ): int {
        $adapter = $db ?? self::getAdapter();

        if ($idOrAll === 'all') {
            $rows = $adapter->fetchAll(sprintf('SELECT * FROM %s ORDER BY id ASC', self::TABLE_FAILED_JOBS));
        } else {
            $row = self::findFailed((int) $idOrAll, $adapter);
            $rows = $row ? [$row] : [];
        }

        $retried = 0;
        $now = time();

        foreach ($rows as $failedJob) {
            $adapter->insert(self::TABLE_NAME, [
                'queue' => $failedJob['queue'] ?? 'default',
                'payload' => $failedJob['payload'],
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ]);

            $adapter->delete(self::TABLE_FAILED_JOBS, ['id = ?' => (int) $failedJob['id']]);
            $retried++;
        }

        return $retried;
    }

    /**
     * Delete failed job(s) from zend_failed_jobs.
     *
     * @param  int|string  $idOrAll  Specific failed ID or 'all'
     * @return int Number of deleted failed jobs
     */
    public static function forgetFailed(
        int|string $idOrAll,
        ?Zend_Db_Adapter_Abstract $db = null
    ): int {
        $adapter = $db ?? self::getAdapter();

        if ($idOrAll === 'all') {
            return (int) $adapter->delete(self::TABLE_FAILED_JOBS);
        }

        return (int) $adapter->delete(self::TABLE_FAILED_JOBS, ['id = ?' => (int) $idOrAll]);
    }

    /**
     * Prune old failed jobs from zend_failed_jobs older than retention days.
     */
    public static function pruneFailed(int $days = 30, ?Zend_Db_Adapter_Abstract $db = null): int
    {
        $adapter = $db ?? self::getAdapter();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return (int) $adapter->delete(self::TABLE_FAILED_JOBS, ['failed_at < ?' => $cutoff]);
    }

    /**
     * Alias for retryFailed().
     */
    public static function retry(int|string $idOrAll = 'all', ?Zend_Db_Adapter_Abstract $db = null): int
    {
        return self::retryFailed($idOrAll, $db);
    }

    /**
     * Alias for forgetFailed().
     */
    public static function forget(int|string $idOrAll, ?Zend_Db_Adapter_Abstract $db = null): int
    {
        return self::forgetFailed($idOrAll, $db);
    }

    /**
     * Alias for pruneFailed().
     */
    public static function prune(int $days = 30, ?Zend_Db_Adapter_Abstract $db = null): int
    {
        return self::pruneFailed($days, $db);
    }

    /**
     * Alias for getFailed().
     */
    public static function getFailedJobs(int $limit = 50, int $offset = 0, ?Zend_Db_Adapter_Abstract $db = null): array
    {
        return self::getFailed($limit, $offset, $db);
    }

    /**
     * Get real-time queue metrics.
     *
     * @return array<string, int|string>
     */
    public static function status(?string $queue = null, ?Zend_Db_Adapter_Abstract $db = null): array
    {
        $adapter = $db ?? self::getAdapter();
        $now = time();

        $queueClause = $queue !== null ? ' AND queue = '.$adapter->quote($queue) : '';

        $pending = (int) $adapter->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE reserved_at IS NULL AND available_at <= ?%s', self::TABLE_NAME, $queueClause),
            [$now]
        );

        $reserved = (int) $adapter->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE reserved_at IS NOT NULL%s', self::TABLE_NAME, $queueClause)
        );

        $delayed = (int) $adapter->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE reserved_at IS NULL AND available_at > ?%s', self::TABLE_NAME, $queueClause),
            [$now]
        );

        $failedClause = $queue !== null ? ' WHERE queue = '.$adapter->quote($queue) : '';
        $failed = (int) $adapter->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s%s', self::TABLE_FAILED_JOBS, $failedClause)
        );

        return [
            'queue' => $queue ?? 'all',
            'pending' => $pending,
            'reserved' => $reserved,
            'delayed' => $delayed,
            'total_active' => $pending + $reserved + $delayed,
            'failed' => $failed,
        ];
    }

    /**
     * Flush jobs from active or failed queue.
     *
     * @param  string  $type  'active', 'failed', or 'all'
     * @param  string|null  $queue  Optional queue filter
     * @return int Total deleted records
     */
    public static function flush(
        string $type = 'all',
        ?string $queue = null,
        ?Zend_Db_Adapter_Abstract $db = null
    ): int {
        $adapter = $db ?? self::getAdapter();
        $deleted = 0;

        $where = [];
        if ($queue !== null) {
            $where['queue = ?'] = $queue;
        }

        if (in_array($type, ['active', 'all'], true)) {
            $deleted += (int) $adapter->delete(self::TABLE_NAME, $where);
        }

        if (in_array($type, ['failed', 'all'], true)) {
            $deleted += (int) $adapter->delete(self::TABLE_FAILED_JOBS, $where);
        }

        return $deleted;
    }

    /**
     * Execute a job synchronously in the current process.
     *
     * @param  string  $job  Job class name or short name
     * @param  array<string, mixed>  $payload  Job arguments
     * @return int 0 representing synchronous completion
     */
    public static function executeSync(string $job, array $payload = []): int
    {
        $jobClass = self::resolveJobClass($job) ?? $job;

        if (! class_exists($jobClass)) {
            throw new RuntimeException("Cannot execute job synchronously: class '{$jobClass}' not found.");
        }

        $instance = new $jobClass;

        if ($instance instanceof JobInterface) {
            $instance->handle($payload);
        } elseif (is_callable([$instance, 'handle'])) {
            $instance->handle($payload);
        } else {
            throw new RuntimeException("Job '{$jobClass}' must implement JobInterface or define handle().");
        }

        return 0;
    }

    /**
     * Resolve a job class name to FQCN if possible.
     */
    public static function resolveJobClass(string $jobIdentifier): ?string
    {
        if (class_exists($jobIdentifier)) {
            return $jobIdentifier;
        }

        $namespaced = "App\\Jobs\\{$jobIdentifier}";
        if (class_exists($namespaced)) {
            return $namespaced;
        }

        return null;
    }

    /**
     * Get class base name without namespace.
     */
    public static function getJobShortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * Parse delay into seconds.
     */
    private static function parseDelay(int|DateInterval|DateTimeInterface $delay): int
    {
        if ($delay instanceof DateTimeInterface) {
            return max(0, $delay->getTimestamp() - time());
        }

        if ($delay instanceof DateInterval) {
            $now = new DateTime;
            $target = (clone $now)->add($delay);

            return max(0, $target->getTimestamp() - $now->getTimestamp());
        }

        return max(0, $delay);
    }

    /**
     * Create the database tables for jobs and failed jobs if they do not exist.
     *
     * @param  bool  $force  Whether to drop existing tables before creating
     * @param  Zend_Db_Adapter_Abstract|null  $db  Optional explicit DB adapter
     * @return array{created: array<string>, dropped: array<string>}
     */
    public static function createTables(bool $force = false, ?Zend_Db_Adapter_Abstract $db = null): array
    {
        $adapter = $db ?? self::getAdapter();
        $dropped = [];
        $created = [];

        if ($force) {
            $adapter->query('DROP TABLE IF EXISTS `'.self::TABLE_NAME.'`');
            $adapter->query('DROP TABLE IF EXISTS `'.self::TABLE_FAILED_JOBS.'`');
            $dropped[] = self::TABLE_NAME;
            $dropped[] = self::TABLE_FAILED_JOBS;
        }

        $jobsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `zend_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL DEFAULT 'default',
  `payload` longtext NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_zend_jobs_queue_reserved_available` (`queue`, `reserved_at`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
SQL;

        $failedJobsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `zend_failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection` varchar(255) NOT NULL DEFAULT 'database',
  `queue` varchar(255) NOT NULL DEFAULT 'default',
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_zend_failed_jobs_queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
SQL;

        try {
            $adapter->query($jobsSql);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'unknown collation')) {
                $jobsSqlFallback = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $jobsSql);
                $adapter->query($jobsSqlFallback);
            } else {
                throw $e;
            }
        }
        $created[] = self::TABLE_NAME;

        try {
            $adapter->query($failedJobsSql);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'unknown collation')) {
                $failedJobsSqlFallback = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $failedJobsSql);
                $adapter->query($failedJobsSqlFallback);
            } else {
                throw $e;
            }
        }
        $created[] = self::TABLE_FAILED_JOBS;

        return [
            'created' => $created,
            'dropped' => $dropped,
        ];
    }

    /**
     * Retrieve default database adapter.
     */
    private static function getAdapter(): Zend_Db_Adapter_Abstract
    {
        $adapter = Zend_Db_Table::getDefaultAdapter();
        if ($adapter === null) {
            throw new RuntimeException('Zend_Db default adapter is not initialized.');
        }

        return $adapter;
    }
}
