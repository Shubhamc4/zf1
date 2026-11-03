<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table;
use Zend_Registry;
use ZF1\Console\Command;
use ZF1\Queue\Queue;
use ZF1\System\MaintenanceMode;

final class AboutCommand extends Command
{
    protected string $name = 'about';

    protected array $aliases = ['system:about', 'system:info'];

    protected string $description = 'Display an overview of the application, environment, and system diagnostics';

    public function handle(): int
    {
        $this->line('');
        $this->info('  ZF1 Application Diagnostics & System Overview');
        $this->line('');

        // 1. Application Section
        $this->renderSection('Application', [
            'Application Name' => (string) env('APP_NAME', defined('APP_NAME') ? (string) APP_NAME : 'ZF1'),
            'Environment' => defined('APPLICATION_ENV') ? APPLICATION_ENV : (string) env('APP_ENV', 'production'),
            'Debug Mode' => (bool) env('APP_DEBUG', false) ? "\033[33mENABLED\033[0m" : 'OFF',
            'URL' => (string) env('APP_URL', 'http://localhost'),
            'Maintenance Mode' => MaintenanceMode::isDown() ? "\033[31mACTIVE (503)\033[0m" : "\033[32mOFF (Live)\033[0m",
            'Timezone' => date_default_timezone_get(),
        ]);

        // 2. PHP & Server Section
        $opcacheEnabled = extension_loaded('Zend OPcache') && (bool) ini_get('opcache.enable');
        $requiredExtensions = ['curl', 'json', 'mbstring', 'openssl', 'pdo'];
        $missingExt = array_filter($requiredExtensions, fn ($ext) => ! extension_loaded($ext));

        $this->renderSection('PHP & Runtime', [
            'PHP Version' => PHP_VERSION.' ('.(PHP_INT_SIZE * 8).'-bit)',
            'Interface (SAPI)' => PHP_SAPI,
            'Operating System' => PHP_OS_FAMILY.' ('.PHP_OS.')',
            'Memory Limit' => ini_get('memory_limit') ?: 'unlimited',
            'Max Execution Time' => ini_get('max_execution_time') ? ini_get('max_execution_time').'s' : 'unlimited',
            'OPcache' => $opcacheEnabled ? "\033[32mENABLED\033[0m" : 'DISABLED',
            'Extensions Check' => empty($missingExt)
                ? "\033[32mAll core extensions loaded\033[0m (curl, json, mbstring, openssl, pdo)"
                : "\033[31mMissing: ".implode(', ', $missingExt)."\033[0m",
        ]);

        // 3. Database Section
        $dbInfo = ['Status' => 'Not Configured'];
        try {
            $db = null;
            if (class_exists('Zend_Db_Table')) {
                $db = Zend_Db_Table::getDefaultAdapter();
            }
            if ($db === null && class_exists('Zend_Registry') && Zend_Registry::isRegistered('db')) {
                $db = Zend_Registry::get('db');
            }

            if ($db instanceof Zend_Db_Adapter_Abstract) {
                $config = $db->getConfig();
                $driver = get_class($db);
                $dbName = $config['dbname'] ?? 'unknown';
                $dbHost = $config['host'] ?? 'localhost';

                // Ping database
                $db->query('SELECT 1')->fetch();
                $dbInfo = [
                    'Connection' => "\033[32mCONNECTED\033[0m",
                    'Adapter' => $driver,
                    'Database' => (string) $dbName,
                    'Host' => (string) $dbHost,
                ];
            }
        } catch (Throwable $e) {
            $dbInfo = [
                'Connection' => "\033[31mFAILED\033[0m ({$e->getMessage()})",
            ];
        }
        $this->renderSection('Database', $dbInfo);

        // 4. Storage & Cache
        $storageDir = storage_path();
        $cacheDir = storage_path('cache');
        $logsDir = storage_path('logs');

        $this->renderSection('Filesystem & Permissions', [
            'Storage Path' => $storageDir.' '.(is_writable($storageDir) ? "[\033[32mwritable\033[0m]" : "[\033[31mnot writable\033[0m]"),
            'Cache Directory' => $cacheDir.' '.(is_writable($cacheDir) ? "[\033[32mwritable\033[0m]" : "[\033[31mnot writable\033[0m]"),
            'Logs Directory' => $logsDir.' '.(is_writable($logsDir) ? "[\033[32mwritable\033[0m]" : "[\033[31mnot writable\033[0m]"),
        ]);

        // 5. Queue Status
        $queueInfo = ['Status' => 'Not available'];
        try {
            if (class_exists(Queue::class)) {
                $stat = Queue::status();
                $failed = (int) ($stat['failed'] ?? 0);
                $queueInfo = [
                    'Tables' => "\033[32mInstalled\033[0m",
                    'Pending Jobs' => (string) ($stat['pending'] ?? 0),
                    'Reserved Jobs' => (string) ($stat['reserved'] ?? 0),
                    'Failed Jobs' => $failed > 0 ? "\033[31m{$failed}\033[0m" : '0',
                ];
            }
        } catch (Throwable) {
            $queueInfo = ['Status' => 'Tables not setup (run: php zf queue:table)'];
        }
        $this->renderSection('Queue & Background Jobs', $queueInfo);

        $this->line('');

        return 0;
    }

    /**
     * Render a labeled section block.
     *
     * @param  array<string, string>  $rows
     */
    private function renderSection(string $title, array $rows): void
    {
        $this->line("\033[33m  {$title}\033[0m");

        $maxKeyWidth = 24;
        foreach (array_keys($rows) as $k) {
            if (mb_strwidth((string) $k) > $maxKeyWidth) {
                $maxKeyWidth = mb_strwidth((string) $k);
            }
        }

        foreach ($rows as $label => $value) {
            $dots = str_repeat('.', max(2, ($maxKeyWidth + 4) - mb_strwidth((string) $label)));
            $this->line("    {$label} {$dots} {$value}");
        }

        $this->line('');
    }
}
