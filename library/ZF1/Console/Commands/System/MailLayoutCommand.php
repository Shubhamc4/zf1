<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use Throwable;
use ZF1\Console\Command;
use ZF1\Mail\EmailLayout;

final class MailLayoutCommand extends Command
{
    protected string $signature = 'mail:layout {--publish : Publish customizable email layout template to application/views/scripts/emails/layout.phtml} {--force : Overwrite existing layout template if it already exists}';

    protected array $aliases = [
        'make:mail-layout',
        'email:layout',
    ];

    protected string $description = 'Publish or check the email layout template';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $publish = (bool) $this->option('publish') || $this->getName() === 'make:mail-layout';

        $projectPath = EmailLayout::getProjectPath();
        $isPublished = EmailLayout::isPublished();

        if ($publish) {
            try {
                $path = EmailLayout::publish(null, $force);
                $this->info("✔ Published email layout template to: {$path}");
                $this->comment('  You can now customize this template to style all outgoing application emails.');

                return 0;
            } catch (Throwable $e) {
                $this->error("✖ {$e->getMessage()}");

                return 1;
            }
        }

        if ($isPublished) {
            $this->info("✔ Project-specific email layout is published at: {$projectPath}");
            $this->comment('  All outgoing emails will use this template.');
            $this->comment('  Use --publish --force to reset to the default framework layout.');
        } else {
            $this->line('Framework default email layout is currently active.');
            $this->comment("Run 'php zf mail:layout --publish' to publish it to {$projectPath}");
        }

        return 0;
    }
}
