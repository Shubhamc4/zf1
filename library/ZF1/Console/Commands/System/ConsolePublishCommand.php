<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use ZF1\Console\Command;
use ZF1\Console\WebConsole;

/**
 * Command to publish the customizable Web Console view template.
 */
final class ConsolePublishCommand extends Command
{
    protected string $signature = 'console:publish {--force : Overwrite existing template if already published}';

    protected array $aliases = [
        'make:console-view',
        'console:view',
    ];

    protected string $description = 'Publish customizable web console HTML/CSS template to application/views/scripts/console/index.phtml';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        try {
            $dest = WebConsole::publishView(null, $force);
            $this->info("✔ Web Console view published to: {$dest}");
            $this->comment('  You can now customize the HTML, CSS, shortcuts, and themes in this file.');
            $this->comment("  Web Console endpoint: \033[32m".WebConsole::path()."\033[0m");

            return 0;
        } catch (Throwable $e) {
            $this->error("✖ {$e->getMessage()}");

            return 1;
        }
    }
}
