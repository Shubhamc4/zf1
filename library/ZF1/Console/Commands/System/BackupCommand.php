<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use ZF1\Console\Command;
use ZF1\System\DatabaseBackup;

final class BackupCommand extends Command
{
    protected string $signature = 'db:backup {--days=3 : Number of days of backups to retain}';

    protected string $description = 'Create a compressed gzip database backup and prune old ones';

    public function handle(): int
    {
        $backupOptions = [];
        if ($this->hasOption('days')) {
            $backupOptions['days'] = (int) $this->option('days');
        }

        try {
            $this->comment('Starting automated database backup...');
            $result = DatabaseBackup::run($backupOptions);

            $this->info("✔ Backup created successfully in {$result['duration']}s");
            $displayFile = $result['filename'] ?? $result['file'] ?? basename($result['path'] ?? 'backup.sql.gz');
            echo "  File:    \033[36m{$displayFile}\033[0m\n";
            echo "  Size:    {$result['size']}\n";
            echo "  Pruned:  {$result['pruned_count']} old backup(s)\n\n";

            return 0;
        } catch (Throwable $e) {
            $this->error('Database backup failed: '.$e->getMessage());

            return 1;
        }
    }
}
