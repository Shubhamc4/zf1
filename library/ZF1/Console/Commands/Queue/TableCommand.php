<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Queue;

use Throwable;
use ZF1\Console\Command;
use ZF1\Queue\Queue;

final class TableCommand extends Command
{
    protected string $signature = 'queue:table {--force : Drop existing tables if present and recreate}';

    protected array $aliases = [
        'queue:install',
        'queue:setup',
    ];

    protected string $description = 'Create database tables for active jobs and failed jobs if not exists';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->comment('Setting up queue database tables...');

        try {
            $result = Queue::createTables($force);

            if (! empty($result['dropped'])) {
                foreach ($result['dropped'] as $table) {
                    $this->comment("  - Dropped existing table `{$table}`");
                }
            }

            foreach ($result['created'] as $table) {
                $this->info("  ✔ Table `{$table}` ensured.");
            }

            $this->info(PHP_EOL.'Queue database tables created / verified successfully.');

            return 0;
        } catch (Throwable $e) {
            $this->error('Failed to create queue database tables: '.$e->getMessage());

            return 1;
        }
    }
}
