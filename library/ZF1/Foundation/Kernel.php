<?php

declare(strict_types=1);

namespace ZF1\Foundation;

use ReflectionClass;
use Throwable;
use Zend_Application;
use ZF1\Environment\Env;
use ZF1\System\MaintenanceMode;

/**
 * Base Application Kernel for Zend Framework 1 projects.
 */
abstract class Kernel
{
    protected static ?Zend_Application $application = null;

    /**
     * Bootstrap the application and return the Zend_Application instance.
     *
     * @param  string|null  $env  Explicit environment override (e.g. 'production', 'local', 'testing')
     */
    public static function boot(?string $env = null): Zend_Application
    {
        if (self::$application instanceof Zend_Application) {
            return self::$application;
        }

        self::initializeEnvironment($env);
        self::initializeIncludePaths();

        require_once 'Zend/Application.php';

        $config = self::getConfig();

        self::$application = new Zend_Application(
            APPLICATION_ENV,
            $config
        );

        self::$application->bootstrap();

        return self::$application;
    }

    /**
     * Bootstrap and immediately run the web application.
     */
    public static function run(?string $env = null): void
    {
        self::boot($env)->run();
    }

    /**
     * Get the active Zend_Application instance if booted.
     */
    public static function getApplication(): ?Zend_Application
    {
        return self::$application;
    }

    /**
     * Reset the active application instance (useful for tests or daemon workers).
     */
    public static function reset(): void
    {
        self::$application = null;
    }

    /**
     * Explicit project root override, if set.
     */
    protected static ?string $projectRoot = null;

    /**
     * Extra include paths registered dynamically.
     *
     * @var array<string>
     */
    protected static array $extraIncludePaths = [];

    /**
     * Manually set the project root directory.
     */
    public static function setProjectRoot(string $path): void
    {
        static::$projectRoot = rtrim($path, '/\\');
    }

    /**
     * Get project root directory.
     */
    public static function getProjectRoot(): string
    {
        if (static::$projectRoot !== null) {
            return static::$projectRoot;
        }

        if (defined('APPLICATION_PATH')) {
            return dirname(APPLICATION_PATH);
        }

        if (static::class !== self::class) {
            try {
                $ref = new ReflectionClass(static::class);
                $file = $ref->getFileName();
                if ($file && file_exists($file)) {
                    return dirname($file, 2);
                }
            } catch (Throwable) {
                // Ignore and proceed
            }
        }

        // If inside a vendor package: <project-root>/vendor/...
        $vendorPos = strpos(__DIR__, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);
        if ($vendorPos !== false) {
            return substr(__DIR__, 0, $vendorPos);
        }

        // Standalone or local package development fallback
        return dirname(__DIR__, 3);
    }

    /**
     * Get application directory path.
     */
    public static function getApplicationPath(): string
    {
        return self::getProjectRoot().'/application';
    }

    /**
     * Get storage directory path (normalized without trailing slash).
     */
    public static function getStoragePath(): string
    {
        return self::getProjectRoot().'/storage';
    }

    /**
     * Get public directory path.
     */
    public static function getPublicPath(): string
    {
        return self::getProjectRoot().'/public';
    }

    /**
     * Explicit configuration file path or configuration array override.
     *
     * @var string|array<string, mixed>|null
     */
    protected static string|array|null $config = null;

    /**
     * Set explicit application configuration path or configuration array.
     *
     * @param  string|array<string, mixed>  $config
     */
    public static function setConfig(string|array $config): void
    {
        static::$config = $config;
    }

    /**
     * Set explicit application configuration file path.
     */
    public static function setConfigPath(string $path): void
    {
        static::$config = $path;
    }

    /**
     * Get application configuration path (supporting .php, .ini, etc.).
     */
    public static function getConfigPath(): string
    {
        if (is_string(static::$config) && static::$config !== '') {
            return static::$config;
        }

        $configsDir = self::getApplicationPath().'/configs';

        $candidates = [
            $configsDir.'/application.php',
            $configsDir.'/application.ini',
            $configsDir.'/app.php',
            $configsDir.'/app.ini',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return $configsDir.'/application.ini';
    }

    /**
     * Get resolved application configuration (array or config file path).
     *
     * Supports both .php files (returning configuration arrays with optional
     * environment sections) and traditional .ini files.
     *
     * @return string|array<string, mixed>
     */
    public static function getConfig(): string|array
    {
        if (is_array(static::$config)) {
            return static::$config;
        }

        $configPath = self::getConfigPath();

        if (str_ends_with(strtolower($configPath), '.php') && file_exists($configPath)) {
            $config = include $configPath;
            if (is_array($config)) {
                $env = defined('APPLICATION_ENV') ? APPLICATION_ENV : (string) env('APP_ENV', 'production');
                if (isset($config[$env]) && is_array($config[$env])) {
                    return $config[$env];
                }

                return $config;
            }
        }

        return $configPath;
    }

    /**
     * Custom database profiler class. Set to override default ZF1\Database\DatabaseProfiler.
     */
    protected static ?string $databaseProfilerClass = null;

    /**
     * Whether database query profiling & slow query logging is enabled.
     * Override to true in child App\Kernel to enable by default.
     */
    protected static bool $enableDatabaseLogging = false;

    /**
     * Get the database profiler class name.
     */
    public static function getDatabaseProfilerClass(): string
    {
        if (static::$databaseProfilerClass !== null) {
            return static::$databaseProfilerClass;
        }

        $envClass = (string) env('DB_PROFILER_CLASS', '');
        if ($envClass !== '' && class_exists($envClass)) {
            return $envClass;
        }

        return \ZF1\Database\DatabaseProfiler::class;
    }

    /**
     * Check if database query profiling/logging is enabled.
     */
    public static function isDatabaseLoggingEnabled(): bool
    {
        if (static::$enableDatabaseLogging) {
            return true;
        }

        return (bool) env('DB_LOG_ENABLED', env('DB_LOG_SLOW_QUERIES', false));
    }

    /**
     * Environment variables that must be defined and not empty.
     * Override in child Kernel to add project-specific requirements.
     *
     * @return array<string>
     */
    protected static function requiredEnv(): array
    {
        return [];
    }

    /**
     * Hook to configure or validate using native Dotenv library methods.
     * Override in child Kernel to add custom Dotenv rules (e.g. isInteger, isBoolean, notEmpty).
     */
    protected static function configureDotenv(\Dotenv\Dotenv $dotenv): void {}

    /**
     * Set up error reporting, constants, .env, server variables, and maintenance checks.
     */
    protected static function initializeEnvironment(?string $env = null): void
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

        $projectRoot = self::getProjectRoot();

        defined('APPLICATION_PATH') || define('APPLICATION_PATH', $projectRoot.'/application');

        // 2. Load Environment file (.env) via ZF1\Environment\Env and validate required variables
        Env::load($projectRoot, required: static::requiredEnv());

        if (Env::getDotenv() !== null) {
            static::configureDotenv(Env::getDotenv());
        }

        // 3. Define Application Environment
        defined('APPLICATION_ENV') || define('APPLICATION_ENV', $env ?: env('APP_ENV', 'production'));
        defined('APP_NAME') || define('APP_NAME', env('APP_NAME', 'ZF1'));
        defined('SESSION_NAME') || define('SESSION_NAME', env('SESSION_NAME', 'zf1'));

        // 4. Fill CLI environment defaults
        if (PHP_SAPI === 'cli') {
            $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?? 'localhost';
            $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
            $_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? '80';
            $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/zf';
        }

        // 5. Global application variables if present
        if (file_exists(config_path('global.variables.php'))) {
            require_once config_path('global.variables.php');
        }

        // 6. Handle Maintenance Mode (web requests only)
        if (PHP_SAPI !== 'cli') {
            MaintenanceMode::check();
        }
    }

    /**
     * Add one or more directories to PHP include_path.
     *
     * @param  string|array<string>  $path
     */
    public static function addIncludePath(string|array $path): void
    {
        $paths = is_array($path) ? $path : [$path];
        foreach ($paths as $p) {
            $p = rtrim($p, '/\\');
            if ($p !== '' && ! in_array($p, static::$extraIncludePaths, true)) {
                static::$extraIncludePaths[] = $p;
            }
        }

        if (self::$application !== null) {
            self::initializeIncludePaths();
        }
    }

    /**
     * Custom include paths defined by the child Kernel.
     * Override in child App\Kernel to register project-specific include paths.
     *
     * Example:
     *   return [
     *       public_path('classes'),
     *   ];
     *
     * @return array<string>
     */
    protected static function includePaths(): array
    {
        return [];
    }

    /**
     * Resolve the Zend Framework 1 library directory.
     * Works when ZF1 is in packages/zf1, installed in vendor/, or in local project.
     */
    public static function getZendLibraryPath(): ?string
    {
        $packageLibrary = dirname(__DIR__, 2);
        if (is_file($packageLibrary.'/Zend/Application.php')) {
            return $packageLibrary;
        }

        // 2. In consuming project vendor directory
        $projectRoot = self::getProjectRoot();
        $vendorZf = $projectRoot.'/vendor/shubhamc4/zf1/library';
        if (is_file($vendorZf.'/Zend/Application.php')) {
            return $vendorZf;
        }

        // 3. Fallback: discover any vendor directory containing Zend/Application.php
        $vendorDir = $projectRoot.'/vendor';
        if (is_dir($vendorDir)) {
            $matches = glob($vendorDir.'/*/*/library/Zend/Application.php');
            if (! empty($matches)) {
                return dirname($matches[0], 2);
            }
        }

        return null;
    }

    /**
     * Ensure PHP include_path contains ZF1 and configured library paths.
     */
    public static function ensureIncludePaths(): void
    {
        self::initializeIncludePaths();
    }

    /**
     * Configure PHP include_path for ZF1 and application libraries.
     */
    protected static function initializeIncludePaths(): void
    {
        $paths = [];

        // 1. Zend Framework 1 library
        if ($zfLibrary = self::getZendLibraryPath()) {
            $paths[] = $zfLibrary;
        }

        // 2. Project-defined include paths from child Kernel hook
        foreach (static::includePaths() as $p) {
            if (is_dir($p)) {
                $paths[] = rtrim($p, '/\\');
            }
        }

        // 3. Extra include paths added dynamically via Kernel::addIncludePath()
        foreach (static::$extraIncludePaths as $p) {
            if (is_dir($p)) {
                $paths[] = rtrim($p, '/\\');
            }
        }

        // 4. Current PHP include_path
        $current = explode(PATH_SEPARATOR, get_include_path());
        foreach ($current as $p) {
            if ($p !== '' && $p !== '.' && is_dir($p)) {
                $paths[] = rtrim($p, '/\\');
            }
        }

        $paths[] = '.';

        set_include_path(implode(PATH_SEPARATOR, array_unique($paths)));
    }
}
