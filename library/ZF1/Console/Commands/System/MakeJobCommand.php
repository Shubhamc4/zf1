<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ZF1\Console\Command;

final class MakeJobCommand extends Command
{
    protected string $signature = 'make:job {name : The class name of the job} {--queue=default : Default queue channel} {--force : Overwrite existing job file}';

    protected array $aliases = [
        'job:make',
    ];

    protected string $description = 'Create a new background Queue Job class';

    public function handle(): int
    {
        $rawName = trim((string) $this->argument('name'));
        if ($rawName === '') {
            $this->error('Please specify a job name. Example: php zf make:job ProcessPayment');

            return 1;
        }

        // Normalize class name and ensure 'Job' suffix
        $className = ucfirst($rawName);
        if (! str_ends_with($className, 'Job')) {
            $className .= 'Job';
        }

        $defaultQueue = (string) $this->option('queue', 'default');

        $targetDir = app_path('Jobs');
        ensure_directory_exists($targetDir);
        $targetFile = "{$targetDir}/{$className}.php";

        $force = (bool) $this->option('force');
        if (file_exists($targetFile) && ! $force) {
            $this->error("Job file [{$targetFile}] already exists! Use --force to overwrite.");

            return 1;
        }

        $stub = str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ queue }}'],
            ['App\Jobs', $className, $defaultQueue],
            self::resolveStub('job.stub')
        );

        file_put_contents($targetFile, $stub);

        $this->info("✔ Job [{$className}] created successfully.");
        $this->comment("  Location: {$targetFile}");
        $this->comment("  Dispatch: dispatch({$className}::class, ['key' => 'value']);");

        return 0;
    }
}
