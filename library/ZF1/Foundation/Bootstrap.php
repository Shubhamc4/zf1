<?php

declare(strict_types=1);

namespace ZF1\Foundation;

use Throwable;
use Zend_Application_Bootstrap_Bootstrap;
use Zend_Application_Module_Autoloader;
use Zend_Cache;
use Zend_Cache_Manager;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Exception;
use Zend_Db_Profiler;
use Zend_Db_Table_Abstract;
use Zend_Layout;
use Zend_Loader_Autoloader_Resource;
use Zend_Log;
use Zend_Log_Writer_Stream;
use Zend_Registry;
use Zend_Session;
use Zend_View;
use ZF1\Console\WebConsole;
use ZF1\Console\WebConsolePlugin;
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
            'frontcontroller',
            'view',
            'layout',
            'dbadapter',
            'helpers',
            'logger',
            'webconsole',
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
        \ZF1\Session\Session::start();
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
     * Initialize Front Controller with convention defaults.
     */
    public function _initFrontController(): Zend_Controller_Front
    {
        $front = Zend_Controller_Front::getInstance();

        if ($this->hasPluginResource('frontcontroller')) {
            $res = $this->getPluginResource('frontcontroller')->init();
            if ($res instanceof Zend_Controller_Front) {
                $front = $res;
            }
        }

        if (! $front->getControllerDirectory('default') && is_dir(app_path('controllers'))) {
            $front->addControllerDirectory(app_path('controllers'), 'default');
        }

        if (is_dir(app_path('modules'))) {
            $front->addModuleDirectory(app_path('modules'));
        }

        $front->setParam('displayExceptions', (bool) env('APP_DEBUG', false));

        return $front;
    }

    /**
     * Initialize Layout with auto-detection for layouts/scripts and layouts/.
     */
    public function _initLayout(): ?Zend_Layout
    {
        if ($this->hasPluginResource('layout')) {
            $res = $this->getPluginResource('layout')->init();

            return $res instanceof Zend_Layout ? $res : null;
        }

        $layoutPath = null;
        if (is_dir(app_path('layouts/scripts'))) {
            $layoutPath = app_path('layouts/scripts');
        } elseif (is_dir(app_path('layouts'))) {
            $layoutPath = app_path('layouts');
        }

        if ($layoutPath !== null) {
            $this->bootstrap('frontcontroller');

            return Zend_Layout::startMvc([
                'layoutPath' => $layoutPath,
            ]);
        }

        return null;
    }

    /**
     * Initialize View and attach to ViewRenderer helper.
     */
    public function _initView(): Zend_View
    {
        if ($this->hasPluginResource('view')) {
            $res = $this->getPluginResource('view')->init();
            if ($res instanceof Zend_View) {
                return $res;
            }
        }

        $options = $this->getOptions();
        $viewOptions = $options['resources']['view'] ?? [];

        $view = new Zend_View($viewOptions);
        $view->setEncoding($viewOptions['encoding'] ?? 'UTF-8');

        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $viewRenderer->setView($view);

        Zend_Registry::set('view', $view);

        return $view;
    }

    /**
     * Register Application View Helper paths by convention.
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

            $helperPaths = [
                app_path('View/Helper') => 'Application_View_Helper',
                app_path('views/helpers') => 'Application_View_Helper',
                app_path('views/scripts/helpers') => 'Application_View_Helper',
                app_path('helpers') => 'Helper',
            ];

            foreach ($helperPaths as $path => $prefix) {
                if (is_dir($path)) {
                    $view->addHelperPath($path, $prefix);
                }
            }
        }

        return $view;
    }

    /**
     * Initialize Database Adapter, metadata cache, and SQL modes with convention defaults.
     */
    protected function _initDbAdapter(): ?Zend_Db_Adapter_Abstract
    {
        $this->bootstrap('cache');

        if (Zend_Registry::isRegistered('cacheManager')) {
            $cacheManager = Zend_Registry::get('cacheManager');
            if ($cacheManager instanceof Zend_Cache_Manager && $cacheManager->hasCache('cache')) {
                Zend_Db_Table_Abstract::setDefaultMetadataCache($cacheManager->getCache('cache'));
            }
        }

        $options = $this->getOptions();
        $dbConfig = $options['resources']['db'] ?? [];
        $adapter = $dbConfig['adapter'] ?? (string) env('DB_CONNECTION', 'Pdo_Mysql');
        $params = $dbConfig['params'] ?? [];

        $params['host'] = $params['host'] ?? (string) env('DB_HOST', '127.0.0.1');
        $params['charset'] = $params['charset'] ?? (string) env('DB_CHARSET', 'utf8mb4');
        $params['username'] = $params['username'] ?? (string) env('DB_USERNAME', '');
        $params['password'] = $params['password'] ?? (string) env('DB_PASSWORD', '');
        $params['dbname'] = $params['dbname'] ?? (string) env('DB_DATABASE', '');

        if (! empty(env('DB_PORT'))) {
            $params['port'] = (int) env('DB_PORT');
        }

        $db = null;

        if (! empty($params['dbname']) || ! empty($params['username'])) {
            try {
                $db = Zend_Db::factory($adapter, $params);
                if ($db instanceof Zend_Db_Adapter_Abstract) {
                    Zend_Db_Table_Abstract::setDefaultAdapter($db);
                    Zend_Registry::set('db', $db);
                    $this->getContainer()->db = $db;
                }
            } catch (Throwable $e) {
                error_log('Database initialization failed: '.$e->getMessage());
            }
        } elseif ($this->hasPluginResource('db')) {
            try {
                $res = $this->getPluginResource('db')->init();
                if ($res instanceof Zend_Db_Adapter_Abstract) {
                    $db = $res;
                    Zend_Registry::set('db', $db);
                    $this->getContainer()->db = $db;
                }
            } catch (Throwable $e) {
                error_log('Plugin db initialization failed: '.$e->getMessage());
            }
        }

        $defaultSqlMode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
        $sqlMode = $params['sql_mode_override'] ?? $defaultSqlMode;

        if ($db instanceof Zend_Db_Adapter_Abstract && $sqlMode) {
            try {
                $db->query("SET SESSION sql_mode = '{$sqlMode}'");
            } catch (Zend_Db_Exception $e) {
                error_log('Failed to set SQL mode: '.$e->getMessage());
            }
        }

        if ($db instanceof Zend_Db_Adapter_Abstract) {
            if ($profiler = $this->createDatabaseProfiler()) {
                $db->setProfiler($profiler);
            }
        }

        return $db;
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
        $timezone = defined('APP_TIMEZONE')
            ? APP_TIMEZONE
            : (string) env('APP_TIMEZONE', env('TIMEZONE', 'Africa/Lusaka'));

        defined('APP_TIMEZONE') || define('APP_TIMEZONE', $timezone);
        defined('TIMEZONE') || define('TIMEZONE', $timezone);
        date_default_timezone_set($timezone);
    }

    /**
     * Initialize Logger with Stream Writer and Discord Log Writer.
     */
    public function _initLogger(): ?Zend_Log
    {
        $logger = null;

        if ($this->hasPluginResource('log')) {
            try {
                $res = $this->getPluginResource('log')->init();
                if ($res instanceof Zend_Log) {
                    $logger = $res;
                }
            } catch (Throwable) {
                // Ignore plugin initialization error
            }
        }

        if ($logger === null) {
            $logFile = ensure_directory_exists(self::getDefaultLogFile());
            $logger = new Zend_Log;
            $logger->addWriter(new Zend_Log_Writer_Stream($logFile, 'a'));
        }

        $logger->addWriter(new DiscordLogWriter);
        Zend_Registry::set('logger', $logger);

        return $logger;
    }

    /**
     * Initialize Global PHP Error, Uncaught Exception, and Fatal Shutdown Handler.
     */
    public function _initErrorHandling(): void
    {
        ini_set('display_startup_errors', '0');
        ini_set('display_errors', '0');

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
     * Initialize Web Console endpoint and register the front controller plugin.
     */
    public function _initWebConsole(): void
    {
        if (PHP_SAPI === 'cli' || ! WebConsole::isEnabled()) {
            return;
        }

        $this->bootstrap('frontcontroller');
        $front = $this->getResource('frontcontroller');

        if (! ($front instanceof Zend_Controller_Front)) {
            $front = Zend_Controller_Front::getInstance();
        }

        $front->registerPlugin(new WebConsolePlugin);
    }

    /**
     * Authorize access to the Web Console.
     * Override in application Bootstrap to customize authorization logic.
     *
     * Example in application/Bootstrap.php:
     *   public function authorizeWebConsole(Zend_Controller_Request_Http $request): bool
     *   {
     *       return ! empty($this->roleid) && $this->roleid === 1;
     *   }
     */
    public function authorizeWebConsole(Zend_Controller_Request_Http $request): bool
    {
        if ($gate = WebConsole::getAuthGate()) {
            return (bool) call_user_func($gate, $request, $this);
        }

        if (WebConsole::isAllowedDuringMaintenance()
            && class_exists(\ZF1\System\MaintenanceMode::class)
            && \ZF1\System\MaintenanceMode::isDown()
        ) {
            return true;
        }

        if (function_exists('is_production')) {
            return ! is_production();
        }

        return defined('APPLICATION_ENV') && APPLICATION_ENV !== 'production';
    }

    /**
     * Get formatted current datetime.
     */
    public static function now(string $format = 'Y-m-d H:i:s', ?int $timestamp = null): string
    {
        return date($format, $timestamp ?? time());
    }

    /**
     * Get the default log file path.
     */
    public static function getDefaultLogFile(): string
    {
        return storage_path('logs/zf1.log');
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
            $logger = Zend_Registry::get('logger');
            if ($logger instanceof Zend_Log) {
                $logger->log($message, $type);

                return;
            }
        }

        $logFile = ensure_directory_exists(self::getDefaultLogFile());

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
     * Log message into queue worker file (disabled by default).
     * Enable via QUEUE_LOG_TO_FILE=true in .env.
     */
    public static function queueLog(string|array $message, int $type = Zend_Log::INFO): void
    {
        if (! (bool) env('QUEUE_LOG_TO_FILE', false) && ! (bool) env('QUEUE_FILE_LOG', false)) {
            return;
        }

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
