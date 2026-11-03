<?php

declare(strict_types=1);

namespace ZF1\Foundation;

use Throwable;
use Zend_Application_Bootstrap_Bootstrap;
use Zend_Application_Module_Autoloader;
use Zend_Cache;
use Zend_Cache_Manager;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Exception;
use Zend_Db_Profiler;
use Zend_Db_Table_Abstract;
use Zend_Loader_Autoloader_Resource;
use Zend_Log;
use Zend_Log_Writer_Stream;
use Zend_Registry;
use Zend_Session;
use ZF1\Logging\DiscordLogWriter;
use ZF1\Logging\UnifiedErrorHandler;

/**
 * Base Application Bootstrap for Zend Framework 1 projects.
 */
abstract class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    /**
     * Get class resource names with foundational infrastructure resources prioritized.
     *
     * @return array<string>
     */
    public function getClassResourceNames(): array
    {
        $names = parent::getClassResourceNames();

        $priority = [
            'errorhandling',
            'timezone',
            'base',
            'resourceautoloader',
            'moduleautoloader',
            'zendsession',
            'cache',
            'dbadapter',
            'helpers',
            'logger',
        ];

        $front = [];
        foreach ($priority as $name) {
            if (in_array($name, $names, true)) {
                $front[] = $name;
            }
        }

        return array_values(array_unique(array_merge($front, $names)));
    }

    /**
     * Start and configure Zend Sessions.
     */
    public function _initZendSession(): void
    {
        Zend_Session::setOptions([
            'name' => self::getSessionName(),
            'use_only_cookies' => true,
            'remember_me_seconds' => 864000,
            'hash_function' => 'sha512',
            'cookie_httponly' => true,
        ]);
        Zend_Session::start();
    }

    /**
     * Initialize Base Autoloader.
     */
    public function _initBase(): void
    {
        require_once 'Zend/Loader/Autoloader.php';
    }

    /**
     * Initialize Application Resource Autoloader for Models.
     */
    public function _initResourceAutoloader(): Zend_Loader_Autoloader_Resource
    {
        return (new Zend_Loader_Autoloader_Resource([
            'namespace' => 'app',
            'basePath' => app_path(),
        ]))->addResourceType('model', 'models', 'Model');
    }

    /**
     * Initialize Module Autoloader.
     */
    public function _initModuleAutoloader(): Zend_Application_Module_Autoloader
    {
        return new Zend_Application_Module_Autoloader([
            'namespace' => 'modules',
            'basePath' => app_path('modules'),
        ]);
    }

    /**
     * Register Application View Helper paths.
     */
    public function _initHelpers(): mixed
    {
        $this->bootstrap('view');

        $view = $this->getResource('view');
        if ($view && method_exists($view, 'addHelperPath')) {
            $coreHelpersDir = dirname(__DIR__).'/View/Helper';
            if (is_dir($coreHelpersDir)) {
                $view->addHelperPath($coreHelpersDir, 'ZF1_View_Helper');
            }

            if (is_dir(app_path('helpers'))) {
                $view->addHelperPath(app_path('helpers'), 'Helper');
            }
        }

        return $view;
    }

    /**
     * Initialize Database Adapter, metadata cache, and SQL modes.
     */
    protected function _initDbAdapter(): void
    {
        $this->bootstrap('db');
        $this->bootstrap('cache');

        if (Zend_Registry::isRegistered('cacheManager')) {
            $cacheManager = Zend_Registry::get('cacheManager');
            if ($cacheManager instanceof Zend_Cache_Manager && $cacheManager->hasCache('cache')) {
                Zend_Db_Table_Abstract::setDefaultMetadataCache($cacheManager->getCache('cache'));
            }
        }

        $db = $this->getResource('db');
        $options = $this->getOptions();

        if ($db && isset($options['resources']['db']['params']['sql_mode_override'])) {
            $sqlMode = $options['resources']['db']['params']['sql_mode_override'];
            try {
                $db->query("SET SESSION sql_mode = '{$sqlMode}'");
            } catch (Zend_Db_Exception $e) {
                error_log('Failed to set SQL mode: '.$e->getMessage());
            }
        }

        // Attach Database Profiler / Slow Query Logger if configured or enabled
        if ($db instanceof Zend_Db_Adapter_Abstract) {
            if ($profiler = $this->createDatabaseProfiler()) {
                $db->setProfiler($profiler);
            }
        }
    }

    /**
     * Factory hook to instantiate the database profiler.
     * Projects can override this method in their application Bootstrap to attach
     * a custom profiler instance with custom dependencies or thresholds.
     */
    protected function createDatabaseProfiler(): ?Zend_Db_Profiler
    {
        if (! static::shouldEnableDatabaseLogging()) {
            return null;
        }

        $profilerClass = $this->getDatabaseProfilerClass();
        $threshold = (float) env('DB_SLOW_QUERY_THRESHOLD', 2.0);

        if (is_subclass_of($profilerClass, \ZF1\Database\DatabaseProfiler::class) || $profilerClass === \ZF1\Database\DatabaseProfiler::class) {
            return new $profilerClass(true, $threshold);
        }

        if (class_exists($profilerClass)) {
            $profiler = new $profilerClass;
            if ($profiler instanceof Zend_Db_Profiler) {
                $profiler->setEnabled(true);

                return $profiler;
            }
        }

        return null;
    }

    /**
     * Resolve the database profiler class name.
     * Projects can override this in their application Bootstrap or App\Kernel.
     */
    protected function getDatabaseProfilerClass(): string
    {
        $kernelClass = class_exists(\App\Kernel::class) ? \App\Kernel::class : Kernel::class;

        return $kernelClass::getDatabaseProfilerClass();
    }

    /**
     * Determine whether database query logging / profiling is enabled.
     */
    protected static function shouldEnableDatabaseLogging(): bool
    {
        $kernelClass = class_exists(\App\Kernel::class) ? \App\Kernel::class : Kernel::class;

        return $kernelClass::isDatabaseLoggingEnabled();
    }

    /**
     * Initialize Application Timezone.
     */
    protected function _initTimeZone(): void
    {
        $timezone = defined('TIMEZONE') ? TIMEZONE : (string) env('TIMEZONE', 'Africa/Lusaka');
        defined('TIMEZONE') || define('TIMEZONE', $timezone);
        date_default_timezone_set($timezone);
    }

    /**
     * Initialize Logger with Discord Log Writer.
     */
    public function _initLogger(): void
    {
        $this->bootstrap('log');
        $logger = $this->getResource('log');

        if ($logger instanceof Zend_Log) {
            $logger->addWriter(new DiscordLogWriter);
        }

        Zend_Registry::set('logger', $logger);
    }

    /**
     * Initialize Global PHP Error, Uncaught Exception, and Fatal Shutdown Handler.
     */
    public function _initErrorHandling(): void
    {
        UnifiedErrorHandler::register();
    }

    /**
     * Initialize Zend Cache Manager.
     */
    public function _initCache(): Zend_Cache_Manager
    {
        $cacheManager = new Zend_Cache_Manager;

        $cacheDir = ensure_directory_exists(storage_path('cache/app'));
        $paginatorDir = ensure_directory_exists(storage_path('cache/paginator'));

        $cacheManager->setCache('cache', Zend_Cache::factory(
            'Core',
            'File',
            [
                'lifetime' => 24 * 3600 * 7,
                'automatic_serialization' => true,
            ],
            [
                'cache_dir' => $cacheDir,
            ]
        ));

        $cacheManager->setCache('paginator', Zend_Cache::factory(
            'Core',
            'File',
            [
                'lifetime' => 3600,
                'automatic_serialization' => true,
            ],
            [
                'cache_dir' => $paginatorDir,
            ]
        ));

        Zend_Registry::set('cacheManager', $cacheManager);

        return $cacheManager;
    }

    /**
     * Get formatted current datetime.
     */
    public static function now(string $format = 'Y-m-d H:i:s', ?int $timestamp = null): string
    {
        return date($format, $timestamp ?? time());
    }

    /**
     * Log message into main log (and Discord).
     */
    public static function log(string|array $message, int $type): void
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES);
        }

        if (Zend_Registry::isRegistered('logger')) {
            Zend_Registry::get('logger')->log($message, $type);

            return;
        }

        $logFile = ensure_directory_exists(storage_path('logs/info.log'));

        try {
            $logger = new Zend_Log;
            $logger->addWriter(new Zend_Log_Writer_Stream($logFile, 'a'));
            $logger->addWriter(new DiscordLogWriter);
            $logger->log($message, $type);
        } catch (Throwable) {
            $line = sprintf("%s EMERG (%d): %s\n", date('c'), $type, (string) $message);
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Log message into debug file.
     */
    public static function debug(string|array $message): void
    {
        try {
            $logFile = ensure_directory_exists(storage_path('logs/debug.log'));

            $logger = new Zend_Log;
            $logger->addWriter(new Zend_Log_Writer_Stream($logFile, 'a'));

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_SLASHES);
            }

            $logger->log($message, Zend_Log::DEBUG);
        } catch (Throwable) {
            // Silently ignore debug file write failure
        }
    }

    /**
     * Log message into queue worker file.
     */
    public static function queueLog(string|array $message, int $type = Zend_Log::INFO): void
    {
        try {
            $logFile = ensure_directory_exists(storage_path('logs/queue.log'));

            $logger = new Zend_Log;
            $logger->addWriter(new Zend_Log_Writer_Stream($logFile, 'a'));

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_SLASHES);
            }

            $logger->log($message, $type);
        } catch (Throwable $e) {
            error_log('[queueLog] Failed to append to queue.log: '.$e->getMessage());
        }
    }

    public static function getSessionName(): string
    {
        return (string) env('SESSION_NAME', defined('SESSION_NAME') ? (string) SESSION_NAME : 'ZF1');
    }
}
