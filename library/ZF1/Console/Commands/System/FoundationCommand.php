<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ZF1\Console\Command;

final class FoundationCommand extends Command
{
    protected string $signature = 'make:foundation {--cli : Scaffold only root CLI file (zf)} {--kernel : Scaffold only application/Kernel.php} {--bootstrap : Scaffold only application/Bootstrap.php} {--force : Overwrite existing files}';

    protected array $aliases = [
        'foundation:install',
        'make:cli',
        'make:kernel',
        'make:bootstrap',
    ];

    protected string $description = 'Scaffold root zf CLI tool, application Kernel, and Bootstrap foundation files if not present';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $onlyCli = (bool) $this->option('cli');
        $onlyKernel = (bool) $this->option('kernel');
        $onlyBootstrap = (bool) $this->option('bootstrap');

        $scaffoldAll = (! $onlyCli && ! $onlyKernel && ! $onlyBootstrap)
            || ($onlyCli && $onlyKernel && $onlyBootstrap);

        $createdCount = 0;

        // 1. Scaffold root zf CLI file
        if ($scaffoldAll || $onlyCli) {
            $cliPath = base_path('zf');
            if (file_exists($cliPath) && ! $force) {
                $this->comment("  [SKIP] {$cliPath} already exists. (Use --force to overwrite)");
            } else {
                $isUpdate = file_exists($cliPath);
                file_put_contents($cliPath, self::resolveStub('zf.stub'));
                @chmod($cliPath, 0755);
                $statusLabel = $isUpdate ? '[UPDATED]' : '[CREATED]';
                $this->info("  {$statusLabel} {$cliPath}");
                $createdCount++;
            }
        }

        // 2. Scaffold application/Kernel.php
        if ($scaffoldAll || $onlyKernel) {
            $appDir = app_path();
            ensure_directory_exists($appDir);

            $kernelPath = app_path('Kernel.php');
            if (file_exists($kernelPath) && ! $force) {
                $this->comment("  [SKIP] {$kernelPath} already exists. (Use --force to overwrite)");
            } else {
                $isUpdate = file_exists($kernelPath);
                file_put_contents($kernelPath, self::resolveStub('kernel.stub'));
                $statusLabel = $isUpdate ? '[UPDATED]' : '[CREATED]';
                $this->info("  {$statusLabel} {$kernelPath}");
                $createdCount++;
            }
        }

        // 3. Scaffold application/Bootstrap.php
        if ($scaffoldAll || $onlyBootstrap) {
            $appDir = app_path();
            ensure_directory_exists($appDir);

            $bootstrapPath = app_path('Bootstrap.php');
            if (file_exists($bootstrapPath) && ! $force) {
                $this->comment("  [SKIP] {$bootstrapPath} already exists. (Use --force to overwrite)");
            } else {
                $isUpdate = file_exists($bootstrapPath);
                file_put_contents($bootstrapPath, self::resolveStub('bootstrap.stub'));
                $statusLabel = $isUpdate ? '[UPDATED]' : '[CREATED]';
                $this->info("  {$statusLabel} {$bootstrapPath}");
                $createdCount++;
            }
        }

        if ($createdCount > 0) {
            $this->info('✔ Foundation scaffolded successfully.');
        } else {
            $this->line('No files were modified.');
        }

        return 0;
    }
}
