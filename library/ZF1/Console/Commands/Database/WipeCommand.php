<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Database;

use Throwable;
use ZF1\Console\Command;
use ZF1\Database\DB;

/**
 * Command to drop all database tables, views, and types.
 */
final class WipeCommand extends Command
{
    protected string $signature = 'db:wipe {--database= : Specify database connection name} {--force : Force the operation to run in production} {--drop-views : Drop all views as well}';

    protected string $description = 'Drop all tables, views, and types in the active database';

    public function handle(): int
    {
        $isProduction = function_exists('is_production') ? is_production() : (defined('APPLICATION_ENV') && APPLICATION_ENV === 'production');
        $force = (bool) $this->option('force');

        if ($isProduction && ! $force) {
            $this->warn('**************************************');
            $this->warn('*     Application In Production!     *');
            $this->warn('**************************************');

            if (! $this->confirm('Do you really wish to drop ALL tables in the production database?', false)) {
                $this->comment('Command cancelled.');

                return 1;
            }
        }

        try {
            $db = DB::connection($this->option('database') ? (string) $this->option('database') : null);
        } catch (Throwable $e) {
            $this->error('Failed to connect to database: '.$e->getMessage());

            return 1;
        }

        $config = $db->getConfig();
        $dbName = $config['dbname'] ?? 'default';

        $this->comment("Wiping database [{$dbName}]...");

        try {
            // Disable foreign key checks
            try {
                $db->query('SET FOREIGN_KEY_CHECKS = 0');
            } catch (Throwable) {
                // Ignore if not MySQL
            }

            // Drop views if requested
            if ($this->option('drop-views')) {
                try {
                    $views = $db->fetchCol('SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()');
                    foreach ($views as $view) {
                        $db->query("DROP VIEW IF EXISTS `{$view}`");
                        $this->line("  Dropped view: \033[33m{$view}\033[0m");
                    }
                } catch (Throwable) {
                    // Ignore view drop error if unsupported
                }
            }

            // Drop all tables
            $tables = $db->listTables();
            if (empty($tables)) {
                $this->info('  Database is already empty.');

                return 0;
            }

            foreach ($tables as $table) {
                $db->query("DROP TABLE IF EXISTS `{$table}`");
                $this->line("  Dropped table: \033[31m{$table}\033[0m");
            }

            // Re-enable foreign key checks
            try {
                $db->query('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Throwable) {
                // Ignore if not MySQL
            }

            $this->line('');
            $this->info('✔ Dropped all '.count($tables)." table(s) successfully from database [{$dbName}].");

            return 0;
        } catch (Throwable $e) {
            $this->error('Failed to wipe database: '.$e->getMessage());

            return 1;
        }
    }
}
