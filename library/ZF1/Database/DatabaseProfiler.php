<?php

declare(strict_types=1);

namespace ZF1\Database;

use Throwable;
use Zend_Db_Profiler;
use Zend_Log;
use Zend_Log_Writer_Stream;

/**
 * High-performance database query profiler & slow query logger.
 */
final class DatabaseProfiler extends Zend_Db_Profiler
{
    protected ?Zend_Log $logger = null;

    protected float $totalElapsedTime = 0.0;

    protected float $slowThreshold = 2.0;

    protected bool $logAll = false;

    /**
     * @param  bool  $enabled  Whether profiling is active
     * @param  float|null  $slowThreshold  Threshold in seconds to trigger logging (default from env or 2.0s)
     */
    public function __construct(bool $enabled = true, ?float $slowThreshold = null)
    {
        parent::__construct($enabled);

        $this->slowThreshold = $slowThreshold ?? (float) env('DB_SLOW_QUERY_THRESHOLD', 2.0);
        $this->logAll = (bool) env('DB_LOG_ALL', false);

        // Filter fast queries inside Zend_Db_Profiler
        if (! $this->logAll) {
            $this->setFilterElapsedSecs((int) ceil($this->slowThreshold));
        }

        $this->initializeLogger();
    }

    /**
     * Set up log writer targeting storage/logs/db.log.
     */
    protected function initializeLogger(): void
    {
        try {
            $logFile = ensure_directory_exists(storage_path('logs/db.log'));

            $this->logger = new Zend_Log;
            $this->logger->addWriter(new Zend_Log_Writer_Stream($logFile, 'a'));
        } catch (Throwable) {
            $this->logger = null;
        }
    }

    /**
     * Intercept query completion and log profiling information for slow queries.
     *
     * @param  int  $queryId
     * @return string self::STORED or self::IGNORED
     */
    public function queryEnd($queryId)
    {
        $state = parent::queryEnd($queryId);

        if (! $this->getEnabled() || $state === self::IGNORED || $this->logger === null) {
            return $state;
        }

        $profile = $this->getQueryProfile($queryId);
        if (! $profile) {
            return $state;
        }

        $elapsed = (float) $profile->getElapsedSecs();
        $this->totalElapsedTime += $elapsed;

        // Skip connection handshakes
        if ($profile->getQueryType() === Zend_Db_Profiler::CONNECT) {
            return $state;
        }

        // Only log if query meets slow threshold or logAll is enabled
        if (! $this->logAll && $elapsed < $this->slowThreshold) {
            return $state;
        }

        $origin = $this->resolveOrigin();

        $message = PHP_EOL.'Elapsed Secs: '.round($elapsed, 5).PHP_EOL;
        $message .= 'Query: '.$profile->getQuery().PHP_EOL;

        $params = $profile->getQueryParams();
        if (! empty($params)) {
            $message .= 'Params: '.json_encode($params, JSON_UNESCAPED_SLASHES).PHP_EOL;
        }

        if (! empty($origin['caller'])) {
            $message .= 'Origin: '.$origin['caller'].PHP_EOL;
        }
        if (! empty($origin['stack'])) {
            $message .= 'Stack: '.$origin['stack'].PHP_EOL;
        }

        $this->logger->info($message);

        return $state;
    }

    /**
     * Resolve the originating application file and line from backtrace.
     *
     * @return array{caller: string, stack: string}
     */
    protected function resolveOrigin(): array
    {
        // Limit depth to 15 frames for speed (< 10 microseconds)
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $basePath = base_path().DIRECTORY_SEPARATOR;
        $frames = [];

        foreach ($trace as $frame) {
            if (empty($frame['file'])) {
                continue;
            }

            $file = (string) $frame['file'];
            $line = (int) $frame['line'];

            // Skip Profiler, Zend Framework, and Vendor files
            if (
                str_contains($file, 'DatabaseProfiler.php')
                || str_contains($file, '/Zend/')
                || str_contains($file, '/vendor/')
            ) {
                continue;
            }

            $relFile = ($basePath !== '' && str_contains($file, $basePath))
                ? str_replace($basePath, '', $file)
                : $file;

            $frames[] = "{$relFile}:{$line}";
        }

        if (empty($frames)) {
            return ['caller' => 'Unknown', 'stack' => ''];
        }

        return [
            'caller' => $frames[0],
            'stack' => implode(' <- ', array_slice($frames, 0, 4)),
        ];
    }

    /**
     * Get total elapsed time across all executed queries.
     */
    public function getTotalElapsedTime(): float
    {
        return $this->totalElapsedTime;
    }
}
