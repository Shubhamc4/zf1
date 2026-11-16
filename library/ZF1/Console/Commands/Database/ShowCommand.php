<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Database;

use Throwable;
use ZF1\Console\Command;
use ZF1\Database\DB;

/**
 * Command to display an overview of database tables, rows, storage size, and engine.
 */
final class ShowCommand extends Command
{
    protected string $signature = 'db:show {--database= : Specify database connection name}';

    protected string $description = 'Display an overview of database tables, rows, storage size, and engine';

    public function handle(): int
    {
        try {
            $db = DB::connection($this->option('database') ? (string) $this->option('database') : null);
        } catch (Throwable $e) {
            $this->error('Failed to connect to database: '.$e->getMessage());

            return 1;
        }

        $config = $db->getConfig();
        $dbName = $config['dbname'] ?? 'default';
        $driver = strtolower((string) ($config['adapter'] ?? 'mysqli'));

        $this->line('');
        $this->info("  Database: \033[36m{$dbName}\033[0m (\033[33m{$driver}\033[0m)");
        $this->line('');

        try {
            // MySQL / MariaDB information_schema
            if (str_contains($driver, 'mysql') || str_contains($driver, 'pdo_mysql') || str_contains($driver, 'mysqli')) {
                $sql = 'SELECT TABLE_NAME AS `name`, ENGINE AS `engine`, TABLE_ROWS AS `rows`, (DATA_LENGTH + INDEX_LENGTH) AS `size`, TABLE_COLLATION AS `collation` FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME';
                $tables = $db->fetchAll($sql);
            } else {
                $tableNames = $db->listTables();
                $tables = [];
                foreach ($tableNames as $tName) {
                    $tables[] = [
                        'name' => $tName,
                        'engine' => 'N/A',
                        'rows' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$tName}"),
                        'size' => 0,
                        'collation' => 'N/A',
                    ];
                }
            }

            if (empty($tables)) {
                $this->comment('  No tables found in this database.');

                return 0;
            }

            $rows = [];
            $totalRows = 0;
            $totalSize = 0;

            foreach ($tables as $t) {
                $name = (string) ($t['name'] ?? '');
                $engine = (string) ($t['engine'] ?? 'InnoDB');
                $rowCount = (int) ($t['rows'] ?? 0);
                $sizeBytes = (float) ($t['size'] ?? 0);
                $collation = (string) ($t['collation'] ?? '');

                $totalRows += $rowCount;
                $totalSize += $sizeBytes;

                $formattedSize = function_exists('formatBytes') ? formatBytes($sizeBytes) : round($sizeBytes / 1024 / 1024, 2).' MB';

                $rows[] = [
                    $name,
                    number_format($rowCount),
                    $formattedSize,
                    $engine,
                    $collation,
                ];
            }

            $headers = ['Table', 'Estimated Rows', 'Size', 'Engine', 'Collation'];
            $this->table($headers, $rows);

            $formattedTotalSize = function_exists('formatBytes') ? formatBytes($totalSize) : round($totalSize / 1024 / 1024, 2).' MB';
            $this->line('');
            $this->comment(sprintf('  Total Tables: %d | Total Rows: %s | Total Disk Size: %s', count($tables), number_format($totalRows), $formattedTotalSize));
            $this->line('');

            return 0;
        } catch (Throwable $e) {
            $this->error('Failed to inspect database tables: '.$e->getMessage());

            return 1;
        }
    }
}
