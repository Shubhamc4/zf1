<?php

declare(strict_types=1);

namespace ZF1\System;

use RuntimeException;
use Zend_Db_Table;
use ZF1\Logging\DiscordNotifier;

final class DatabaseBackup
{
    /**
     * Run full database backup with gzip compression and retention pruning.
     *
     * @param  array<string, mixed>  $options  Supported: 'days' (int retention days)
     * @return array<string, mixed> Backup execution results
     */
    public static function run(array $options = []): array
    {
        $startTime = microtime(true);
        $retentionDays = max(1, (int) ($options['days'] ?? 3));
        $backupDir = ensure_directory_exists(base_path('backup'));

        $adapter = Zend_Db_Table::getDefaultAdapter();
        if ($adapter === null) {
            throw new RuntimeException('Zend_Db default adapter is not initialized.');
        }

        $config = $adapter->getConfig();
        $database = (string) ($config['dbname'] ?? '');
        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 3306);
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');

        if (empty($database)) {
            throw new RuntimeException('Database name is not configured.');
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = sprintf('backup-%s-%s.sql.gz', $database, $timestamp);
        $targetPath = "{$backupDir}/{$filename}";
        $latestPath = "{$backupDir}/latest.sql.gz";

        // Build mysqldump command
        $dumpCmd = [
            'mysqldump',
            '--force',
            '--opt',
            '--set-gtid-purged=OFF',
            '--no-tablespaces',
            '--column-statistics=0',
            sprintf('--host=%s', escapeshellarg($host)),
            sprintf('--port=%d', $port),
            sprintf('--user=%s', escapeshellarg($username)),
        ];

        if ($password !== '') {
            $dumpCmd[] = sprintf('--password=%s', escapeshellarg($password));
        }

        $dumpCmd[] = escapeshellarg($database);

        $stderrFile = tempnam(sys_get_temp_dir(), 'db_dump_err_');

        $fullCommand = sprintf(
            '%s 2>%s | gzip -c > %s',
            implode(' ', $dumpCmd),
            escapeshellarg((string) $stderrFile),
            escapeshellarg($targetPath)
        );

        $returnCode = 0;
        $output = [];
        exec($fullCommand, $output, $returnCode);

        $stderr = ($stderrFile && file_exists($stderrFile)) ? trim((string) file_get_contents($stderrFile)) : '';
        if ($stderrFile && file_exists($stderrFile)) {
            @unlink($stderrFile);
        }

        $elapsedSeconds = round(microtime(true) - $startTime, 2);

        if ($returnCode !== 0 || ! file_exists($targetPath) || filesize($targetPath) === 0) {
            if (file_exists($targetPath) && filesize($targetPath) === 0) {
                unlink($targetPath);
            }

            $detail = $stderr !== '' ? " - Error: {$stderr}" : '';
            $errorMessage = sprintf('Database backup failed for %s (mysqldump exited with code %d%s)', $database, $returnCode, $detail);

            DiscordNotifier::error('Database Backup Failed', $errorMessage, [
                'Database' => $database,
                'Host' => "{$host}:{$port}",
                'Environment' => env('APP_ENV', 'production'),
            ]);

            throw new RuntimeException($errorMessage);
        }

        // Update latest.sql.gz copy
        copy($targetPath, $latestPath);

        $fileSizeBytes = (int) filesize($targetPath);
        $formattedSize = self::formatBytes($fileSizeBytes);

        // Prune old backups older than retention period
        $prunedCount = self::pruneOldBackups($backupDir, $retentionDays);

        return [
            'success' => true,
            'database' => $database,
            'file' => $filename,
            'filename' => $filename,
            'path' => $targetPath,
            'size' => $formattedSize,
            'size_bytes' => $fileSizeBytes,
            'duration' => $elapsedSeconds,
            'pruned_count' => $prunedCount,
        ];
    }

    /**
     * Delete backups older than specified retention days.
     */
    public static function pruneOldBackups(string $backupDir, int $retentionDays): int
    {
        $cutoff = time() - ($retentionDays * 86400);
        $pruned = 0;

        $files = glob("{$backupDir}/backup-*.sql.gz");
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $pruned++;
                }
            }
        }

        return $pruned;
    }

    /**
     * Format bytes to human readable format.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return sprintf('%.2f GB', $bytes / 1073741824);
        }
        if ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return "{$bytes} B";
    }
}
