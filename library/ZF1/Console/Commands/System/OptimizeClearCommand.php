<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use Zend_Db_Table_Abstract;
use ZF1\Console\Command;

/**
 * Command to clear cached bootstrap data and metadata.
 */
final class OptimizeClearCommand extends Command
{
    protected string $signature = 'optimize:clear';

    protected string $description = 'Clear all cached bootstrap data, metadata caches, and compiled files';

    public function handle(): int
    {
        $this->comment('Clearing cached bootstrap and optimization files...');

        $metaCache = Zend_Db_Table_Abstract::getDefaultMetadataCache();
        if ($metaCache !== null) {
            try {
                $metaCache->clean();
                $this->info('✔ Database metadata cache cleared.');
            } catch (Throwable $e) {
                $this->error('Failed to clear metadata cache: '.$e->getMessage());
            }
        }

        if (function_exists('storage_path')) {
            $cacheDir = storage_path('cache/app');
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir.'/zend_cache---*');
                if (! empty($files)) {
                    foreach ($files as $file) {
                        @unlink($file);
                    }
                    $this->info('✔ Application cache files cleared.');
                }
            }
        }

        $this->line('');
        $this->info('✔ All optimization caches cleared!');

        return 0;
    }
}
