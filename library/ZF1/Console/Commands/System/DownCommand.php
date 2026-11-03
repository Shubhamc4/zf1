<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ZF1\Console\Command;
use ZF1\System\MaintenanceMode;

final class DownCommand extends Command
{
    protected string $signature = 'down {--message= : Custom maintenance message} {--retry=60 : Retry-After header interval in seconds} {--secret= : Secret key to bypass maintenance mode} {--template= : Custom view template path or script name} {--publish : Publish customizable maintenance view template to application/views/scripts/maintenance.phtml}';

    protected string $description = 'Put the application into maintenance mode';

    public function handle(): int
    {
        if ($this->option('publish')) {
            $path = MaintenanceMode::publishView();
            $this->info("✔ Published maintenance view template to: {$path}");
            $this->comment('  You can now customize this template to style your maintenance page.');

            return 0;
        }

        $options = [];
        if ($this->hasOption('message')) {
            $options['message'] = (string) $this->option('message');
        }
        if ($this->hasOption('retry')) {
            $options['retry'] = (int) $this->option('retry');
        }
        if ($this->hasOption('secret')) {
            $options['secret'] = (string) $this->option('secret');
        }
        if ($this->hasOption('template')) {
            $options['template'] = (string) $this->option('template');
        }

        MaintenanceMode::down($options);
        $this->info('✔ Application is now in maintenance mode.');

        $data = MaintenanceMode::getData();
        if (! empty($data['template'])) {
            echo "  Custom View:   \033[32m{$data['template']}\033[0m\n";
        }
        if (! empty($data['secret'])) {
            $baseUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
            echo "  Bypass Secret: \033[33m{$data['secret']}\033[0m\n";
            echo "  Bypass URL:    \033[36m{$baseUrl}/{$data['secret']}\033[0m\n";
            echo "  Query Bypass:  \033[36m{$baseUrl}/?secret={$data['secret']}\033[0m\n";
            echo "  Cycle Token:   \033[90m{$data['token']}\033[0m\n";
        }
        echo PHP_EOL;

        return 0;
    }
}
