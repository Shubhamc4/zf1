<?php

declare(strict_types=1);

namespace ZF1\Cache;

use Closure;
use Zend_Cache;
use Zend_Cache_Core;
use Zend_Cache_Manager;
use Zend_Registry;

/**
 * Modern Cache Manager and Static Facade for Zend Framework 1.
 *
 * @mixin Zend_Cache_Core
 */
final class Cache
{
    /**
     * Named cache instances.
     *
     * @var array<string, Zend_Cache_Core>
     */
    private static array $stores = [];

    /**
     * Get a named cache store instance (defaults to 'cache').
     */
    public static function store(?string $name = null): Zend_Cache_Core
    {
        $name = $name ?: 'cache';

        if (isset(self::$stores[$name])) {
            return self::$stores[$name];
        }

        $store = self::resolveStore($name);
        self::$stores[$name] = $store;

        return $store;
    }

    /**
     * Retrieve an item from the cache.
     *
     * @param  mixed  $default  Value or Closure returning default
     */
    public static function get(string $key, mixed $default = null, ?string $store = null): mixed
    {
        $cache = self::store($store);

        if ($cache->test($key) !== false) {
            $value = $cache->load($key);
            if ($value !== false) {
                return $value;
            }
        }

        return $default instanceof Closure ? $default() : $default;
    }

    /**
     * Store an item in the cache.
     *
     * @param  array<string>  $tags
     */
    public static function put(string $key, mixed $value, ?int $ttl = null, array $tags = [], ?string $store = null): bool
    {
        return self::store($store)->save($value, $key, $tags, $ttl);
    }

    /**
     * Alias for put().
     *
     * @param  array<string>  $tags
     */
    public static function set(string $key, mixed $value, ?int $ttl = null, array $tags = [], ?string $store = null): bool
    {
        return self::put($key, $value, $ttl, $tags, $store);
    }

    /**
     * Check if an item exists in the cache and is not expired.
     */
    public static function has(string $key, ?string $store = null): bool
    {
        return self::store($store)->test($key) !== false;
    }

    /**
     * Remove an item from the cache.
     */
    public static function forget(string $key, ?string $store = null): bool
    {
        return self::store($store)->remove($key);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  array<string>  $tags
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $callback, array $tags = [], ?string $store = null): mixed
    {
        $cache = self::store($store);

        if ($cache->test($key) !== false) {
            $value = $cache->load($key);
            if ($value !== false) {
                return $value;
            }
        }

        $value = $callback();
        $cache->save($value, $key, $tags, $ttl);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result indefinitely.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  array<string>  $tags
     * @return T
     */
    public static function rememberForever(string $key, callable $callback, array $tags = [], ?string $store = null): mixed
    {
        // 0 in Zend_Cache means infinite lifetime
        return self::remember($key, 0, $callback, $tags, $store);
    }

    /**
     * Flush all items from the cache store.
     *
     * @param  string  $mode  Zend_Cache::CLEANING_MODE_ALL or CLEANING_MODE_MATCHING_TAG
     * @param  array<string>  $tags
     */
    public static function flush(string $mode = Zend_Cache::CLEANING_MODE_ALL, array $tags = [], ?string $store = null): bool
    {
        return self::store($store)->clean($mode, $tags);
    }

    /**
     * Resolve the Zend_Cache_Core instance from Zend_Registry cacheManager.
     */
    private static function resolveStore(string $name): Zend_Cache_Core
    {
        if (Zend_Registry::isRegistered('cacheManager')) {
            $manager = Zend_Registry::get('cacheManager');
            if ($manager instanceof Zend_Cache_Manager && $manager->hasCache($name)) {
                return $manager->getCache($name);
            }
        }

        if (Zend_Registry::isRegistered($name)) {
            $candidate = Zend_Registry::get($name);
            if ($candidate instanceof Zend_Cache_Core) {
                return $candidate;
            }
        }

        // Fallback: Default File cache inside storage/cache/app
        if (class_exists(\ZF1\Foundation\Kernel::class)) {
            \ZF1\Foundation\Kernel::ensureIncludePaths();
        }

        $cacheDir = function_exists('storage_path') ? storage_path('cache/app') : sys_get_temp_dir();
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        return Zend_Cache::factory(
            'Core',
            'File',
            ['lifetime' => 86400 * 7, 'automatic_serialization' => true],
            ['cache_dir' => $cacheDir]
        );
    }

    /**
     * Reset cached store references.
     */
    public static function reset(): void
    {
        self::$stores = [];
    }

    /**
     * Dynamically forward static method calls to the default cache store.
     *
     * @param  array<mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::store()->{$method}(...$arguments);
    }
}
