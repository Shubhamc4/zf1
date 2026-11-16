<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\Database;

use Throwable;
use ZF1\Console\Command;
use ZF1\Database\DB;

/**
 * Command to inspect table schema, columns, data types, and indexes.
 */
final class TableCommand extends Command
{
    protected string $signature = 'db:table {table : The table name to inspect} {--database= : Specify database connection name}';

    protected string $description = 'Inspect table schema, columns, data types, and indexes';

    public function handle(): int
    {
        $tableName = (string) $this->argument('table');

        try {
            $db = DB::connection($this->option('database') ? (string) $this->option('database') : null);
        } catch (Throwable $e) {
            $this->error('Failed to connect to database: '.$e->getMessage());

            return 1;
        }

        try {
            $schema = $db->describeTable($tableName);
        } catch (Throwable $e) {
            $this->error("Table [{$tableName}] not found or could not be inspected: ".$e->getMessage());

            return 1;
        }

        $this->line('');
        $this->info("  Table Schema: \033[36m{$tableName}\033[0m");
        $this->line('');

        $rows = [];
        foreach ($schema as $colName => $col) {
            $rows[] = [
                $colName,
                (string) ($col['DATA_TYPE'] ?? 'varchar'),
                ! empty($col['NULLABLE']) ? 'YES' : 'NO',
                $col['DEFAULT'] !== null ? (string) $col['DEFAULT'] : 'NULL',
                ! empty($col['PRIMARY']) ? 'YES' : 'NO',
                ! empty($col['IDENTITY']) ? 'auto_increment' : '',
            ];
        }

        $headers = ['Column', 'Type', 'Nullable', 'Default', 'Primary Key', 'Extra'];
        $this->table($headers, $rows);

        // Try inspecting indexes if supported
        try {
            $indexRows = $db->fetchAll("SHOW INDEX FROM `{$tableName}`");
            if (! empty($indexRows)) {
                $indexes = [];
                foreach ($indexRows as $idx) {
                    $keyName = (string) ($idx['Key_name'] ?? '');
                    $colName = (string) ($idx['Column_name'] ?? '');
                    $nonUnique = (int) ($idx['Non_unique'] ?? 1);
                    $indexType = (string) ($idx['Index_type'] ?? 'BTREE');

                    $indexes[$keyName]['columns'][] = $colName;
                    $indexes[$keyName]['unique'] = $nonUnique === 0 ? 'YES' : 'NO';
                    $indexes[$keyName]['type'] = $indexType;
                }

                $this->line('');
                $this->comment('  Indexes');
                $this->line('');

                $indexTableRows = [];
                foreach ($indexes as $keyName => $data) {
                    $indexTableRows[] = [
                        $keyName,
                        implode(', ', $data['columns']),
                        $data['unique'],
                        $data['type'],
                    ];
                }

                $this->table(['Key Name', 'Columns', 'Unique', 'Type'], $indexTableRows);
            }
        } catch (Throwable) {
            // Ignore index inspection error on drivers that don't support SHOW INDEX
        }

        $this->line('');

        return 0;
    }
}
