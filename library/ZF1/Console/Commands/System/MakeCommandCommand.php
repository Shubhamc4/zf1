<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ZF1\Console\Command;

final class MakeCommandCommand extends Command
{
    protected string $signature = 'make:command {name : The class name of the command} {--command= : The terminal command signature} {--force : Overwrite existing command file}';

    protected array $aliases = [
        'command:make',
    ];

    protected string $description = 'Create a new ZF1 console command class';

    public function handle(): int
    {
        $rawName = trim((string) $this->argument('name'));
        if ($rawName === '') {
            $this->error('Please specify a command name. Example: php zf make:command ProcessReports');

            return 1;
        }

        // Normalize class name and ensure 'Command' suffix
        $className = ucfirst($rawName);
        if (! str_ends_with($className, 'Command')) {
            $className .= 'Command';
        }

        // Determine terminal signature
        $defaultSignature = strtolower(preg_replace('/(?<!^)[A-Z]/', ':$0', str_replace('Command', '', $className)));
        $signature = $this->hasOption('command') ? (string) $this->option('command') : $defaultSignature;

        $targetDir = app_path('Commands');
        ensure_directory_exists($targetDir);
        $targetFile = "{$targetDir}/{$className}.php";

        $force = (bool) $this->option('force');
        if (file_exists($targetFile) && ! $force) {
            $this->error("Command file [{$targetFile}] already exists! Use --force to overwrite.");

            return 1;
        }

        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ signature }}'],
            ['App\Commands', $className, $signature],
            self::resolveStub('command.stub')
        );

        file_put_contents($targetFile, $stub);

        $this->info("✔ Command [{$className}] created successfully.");
        $this->comment("  Location:  {$targetFile}");
        $this->comment("  Signature: php zf {$signature}");

        return 0;
    }
}
