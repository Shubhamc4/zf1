<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use ZF1\Console\Command;
use ZF1\View\ErrorRenderer;

final class ErrorPublishCommand extends Command
{
    protected string $signature = 'error:publish {--code= : Specific HTTP error code to publish (e.g. 404, 500)} {--path= : Custom destination directory} {--force : Overwrite existing view files}';

    protected string $description = 'Publish customizable error view templates to application/views/error/{code}.phtml';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $codeOption = $this->option('code');
        $pathOption = $this->option('path');

        $code = ($codeOption !== null && $codeOption !== '') ? (int) $codeOption : null;
        $targetDir = ($pathOption !== null && $pathOption !== '') ? (string) $pathOption : null;

        try {
            $published = ErrorRenderer::publishViews($targetDir, $code, $force);

            if (empty($published)) {
                $this->comment('No new error views were published. (Files already exist. Use --force to overwrite)');

                return 0;
            }

            $this->info('✔ Error views successfully published:');
            foreach ($published as $file) {
                $this->line("  \033[32m[PUBLISHED]\033[0m {$file}");
            }

            $this->comment(PHP_EOL.'  You can now customize these templates or edit layout.phtml for shared error styling.');
            $this->comment('  Custom views at application/views/error/{code}.phtml are automatically detected and used.');

            return 0;
        } catch (Throwable $e) {
            $this->error("✖ Failed to publish error views: {$e->getMessage()}");

            return 1;
        }
    }
}
