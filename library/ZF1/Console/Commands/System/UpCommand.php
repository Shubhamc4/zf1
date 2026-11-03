<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ZF1\Console\Command;
use ZF1\System\MaintenanceMode;

final class UpCommand extends Command
{
    protected string $name = 'up';

    protected string $description = 'Bring the application out of maintenance mode';

    public function handle(): int
    {
        MaintenanceMode::up();
        $this->info('✔ Application is now live.'.PHP_EOL);

        return 0;
    }
}
