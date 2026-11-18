<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use ZF1\Console\Command;
use ZF1\Database\Paginator;

final class PaginationPublishCommand extends Command
{
    protected string $signature = 'pagination:publish {--type= : Specific template to publish (e.g. bootstrap-5, bootstrap-4, simple-bootstrap-5, tailwind)} {--path= : Custom destination directory} {--force : Overwrite existing view files}';

    protected string $description = 'Publish customizable pagination view templates to application/views/scripts/pagination/';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $typeOption = $this->option('type');
        $pathOption = $this->option('path');

        $type = ($typeOption !== null && $typeOption !== '') ? (string) $typeOption : null;
        $targetDir = ($pathOption !== null && $pathOption !== '') ? (string) $pathOption : null;

        try {
            $published = Paginator::publishViews($targetDir, $type, $force);

            if (empty($published)) {
                $this->comment('No pagination views were published. (Files already exist. Use --force to overwrite)');

                return 0;
            }

            $this->info('✔ Pagination views successfully published:');
            foreach ($published as $file) {
                $this->line("  \033[32m[PUBLISHED]\033[0m {$file}");
            }

            $this->comment(PHP_EOL.'  You can now customize these templates to match your application styling.');
            $this->comment('  Render them with: $paginator->links() or $paginator->links(\'tailwind\')');

            return 0;
        } catch (Throwable $e) {
            $this->error("✖ Failed to publish pagination views: {$e->getMessage()}");

            return 1;
        }
    }
}
