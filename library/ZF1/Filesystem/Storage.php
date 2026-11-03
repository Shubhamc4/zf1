<?php

declare(strict_types=1);

namespace ZF1\Filesystem;

use Closure;

/**
 * Storage Manager and Static Facade.
 *
 * @method static bool exists(string $path)
 * @method static bool missing(string $path)
 * @method static string|null get(string $path)
 * @method static bool put(string $path, mixed $contents)
 * @method static bool prepend(string $path, string $data)
 * @method static bool append(string $path, string $data)
 * @method static bool delete(string|array $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string mimeType(string $path)
 * @method static string path(string $path = '')
 * @method static string url(string $path = '')
 * @method static string|false putFile(string $directory, array|string $file)
 * @method static string|false putFileAs(string $directory, array|string $file, string $name)
 * @method static void download(string $path, ?string $name = null, array $headers = [])
 * @method static void response(string $path, ?string $name = null, array $headers = [])
 * @method static array<string> files(string $directory = '', bool $recursive = false)
 * @method static array<string> directories(string $directory = '', bool $recursive = false)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $directory)
 */
final class Storage
{
    /** @var array<string, FilesystemAdapter> */
    private static array $disks = [];

    /** @var array<string, Closure> */
    private static array $customDrivers = [];

    /**
     * Get a filesystem disk instance by name.
     */
    public static function disk(?string $name = null): FilesystemAdapter
    {
        $name = $name ?: self::getDefaultDiskName();

        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        self::$disks[$name] = self::resolveDisk($name);

        return self::$disks[$name];
    }

    /**
     * Determine the default disk name.
     */
    public static function getDefaultDiskName(): string
    {
        return (string) env('FILESYSTEM_DISK', 'public');
    }

    /**
     * Manually set or override a disk instance.
     */
    public static function set(string $name, FilesystemAdapter $adapter): void
    {
        self::$disks[$name] = $adapter;
    }

    /**
     * Register a custom driver creator.
     */
    public static function extend(string $driver, Closure $callback): void
    {
        self::$customDrivers[$driver] = $callback;
    }

    /**
     * Resolve a disk instance based on configuration.
     */
    private static function resolveDisk(string $name): FilesystemAdapter
    {
        $config = self::getDiskConfig($name);
        $driver = $config['driver'] ?? 'local';

        if (isset(self::$customDrivers[$driver])) {
            $adapter = self::$customDrivers[$driver]($config);
            if ($adapter instanceof FilesystemAdapter) {
                return $adapter;
            }
        }

        $root = (string) ($config['root'] ?? storage_path("app/{$name}"));
        $url = (string) ($config['url'] ?? '');

        return new FilesystemAdapter($root, $url, $config);
    }

    /**
     * Retrieve configuration for a named disk.
     *
     * @return array<string, mixed>
     */
    private static function getDiskConfig(string $name): array
    {
        // 1. Check application/configs/filesystems.php if present
        $configPath = config_path('filesystems.php');
        if (file_exists($configPath)) {
            $userConfig = require $configPath;
            if (is_array($userConfig) && isset($userConfig['disks'][$name])) {
                return $userConfig['disks'][$name];
            }
        }

        // 2. Built-in disk definitions
        return match ($name) {
            'public' => [
                'driver' => 'local',
                'root' => public_path('uploads'),
                'url' => asset('uploads'),
            ],
            'local' => [
                'driver' => 'local',
                'root' => storage_path('app'),
                'url' => '',
            ],
            'storage' => [
                'driver' => 'local',
                'root' => storage_path(),
                'url' => '',
            ],
            'root' => [
                'driver' => 'local',
                'root' => base_path(),
                'url' => '',
            ],
            default => [
                'driver' => 'local',
                'root' => storage_path("app/{$name}"),
                'url' => '',
            ],
        };
    }

    /**
     * Reset cached disk instances.
     */
    public static function reset(): void
    {
        self::$disks = [];
    }

    /**
     * Forward static calls directly to the default disk.
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::disk()->{$method}(...$arguments);
    }
}
