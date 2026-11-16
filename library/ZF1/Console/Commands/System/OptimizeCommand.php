<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use ZF1\Console\Command;
use ZF1\Database\DB;

/**
 * Command to cache database metadata and warm application caches.
 */
final class OptimizeCommand extends Command
{
    protected string $signature = 'optimize';

    protected string $description = 'Cache database metadata and warm application caches to optimize performance';

    public function handle(): int
    {
        $this->comment('Optimizing framework and application performance...');

        $optimized = 0;

        if (function_exists('storage_path')) {
            ensure_directory_exists(storage_path('cache/app'));
            ensure_directory_exists(storage_path('cache/paginator'));
            ensure_directory_exists(storage_path('logs'));
        }

        try {
            $db = DB::connection();
            $tables = $db->listTables();

            foreach ($tables as $table) {
                try {
                    $db->describeTable($table);
                    $optimized++;
                } catch (Throwable) {
                    // Ignore individual table describe failure
                }
            }

            $this->info("✔ Pre-cached database metadata for {$optimized} table(s).");
        } catch (Throwable $e) {
            $this->comment('  (Database metadata cache skipped: '.$e->getMessage().')');
        }

        $this->line('');
        $this->info('✔ Application optimized successfully!');

        return 0;
    }
}
