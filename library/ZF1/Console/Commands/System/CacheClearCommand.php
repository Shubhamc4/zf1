<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;
use Zend_Cache;
use Zend_Cache_Manager;
use Zend_Db_Table_Abstract;
use Zend_Registry;
use ZF1\Console\Command;

final class CacheClearCommand extends Command
{
    protected string $signature = 'cache:clear {--tags= : Specific cache tags to clean (comma-separated)} {--all : Flush all application caches, managers, and metadata}';

    protected array $aliases = [
        'cache:flush',
    ];

    protected string $description = 'Flush application file caches, Zend_Cache managers, and database metadata caches';

    public function handle(): int
    {
        $tags = $this->hasOption('tags') ? array_filter(array_map('trim', explode(',', (string) $this->option('tags')))) : [];
        $filesDeleted = 0;
        $bytesFreed = 0;

        // 1. Clear storage file caches across all folders in storage/cache/
        $cacheRoot = storage_path('cache');
        if (is_dir($cacheRoot)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if (in_array($item->getFilename(), ['.gitignore', '.gitkeep'], true)) {
                    continue;
                }

                if ($item->isFile()) {
                    $bytesFreed += (int) $item->getSize();
                    if (@unlink($item->getRealPath())) {
                        $filesDeleted++;
                    }
                } elseif ($item->isDir()) {
                    // Preserve top-level cache bucket folders (e.g. app, paginator, translate)
                    $rel = ltrim(str_replace($cacheRoot, '', $item->getRealPath()), '/\\');
                    if (str_contains($rel, DIRECTORY_SEPARATOR)) {
                        @rmdir($item->getRealPath());
                    }
                }
            }
        }

        // Re-ensure default bucket directories exist
        ensure_directory_exists(storage_path('cache/app'));
        ensure_directory_exists(storage_path('cache/paginator'));

        // 2. Clear Zend_Cache_Manager caches if initialized
        $managerFlushed = 0;
        if (class_exists(Zend_Registry::class) && Zend_Registry::isRegistered('cacheManager')) {
            try {
                $manager = Zend_Registry::get('cacheManager');
                if ($manager instanceof Zend_Cache_Manager) {
                    $ref = new ReflectionClass($manager);
                    $prop = $ref->hasProperty('_caches') ? $ref->getProperty('_caches') : null;
                    if ($prop) {
                        $prop->setAccessible(true);
                        $caches = (array) $prop->getValue($manager);
                        foreach ($caches as $cache) {
                            if (is_object($cache) && method_exists($cache, 'clean')) {
                                if (! empty($tags)) {
                                    $cache->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, $tags);
                                } else {
                                    $cache->clean(Zend_Cache::CLEANING_MODE_ALL);
                                }
                                $managerFlushed++;
                            }
                        }
                    }
                }
            } catch (Throwable) {
                // Ignore gracefully if cache manager isn't available
            }
        }

        // 3. Clear database table metadata cache
        if (class_exists(Zend_Db_Table_Abstract::class)) {
            try {
                $metadataCache = Zend_Db_Table_Abstract::getDefaultMetadataCache();
                if ($metadataCache && method_exists($metadataCache, 'clean')) {
                    $metadataCache->clean(Zend_Cache::CLEANING_MODE_ALL);
                }
            } catch (Throwable) {
                // Ignore if db is not configured
            }
        }

        $formattedBytes = self::formatBytes($bytesFreed);
        $this->info("✔ Application cache cleared successfully ({$filesDeleted} files deleted, {$formattedBytes} freed).");

        if ($managerFlushed > 0) {
            $this->comment("  Flushed {$managerFlushed} active Zend_Cache manager pool(s).");
        }

        return 0;
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
