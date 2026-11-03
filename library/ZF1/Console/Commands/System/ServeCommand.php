<?php

declare(strict_types=1);

namespace ZF1\Console\Commands\System;

use ZF1\Console\Command;

final class ServeCommand extends Command
{
    protected string $signature = 'serve {--host=127.0.0.1 : Host IP to bind the local server} {--port=8000 : Port to listen on} {--public= : Public web root directory}';

    protected array $aliases = [
        'server',
    ];

    protected string $description = 'Start the PHP built-in development web server';

    public function handle(): int
    {
        $host = (string) $this->option('host', '127.0.0.1');
        $port = (int) $this->option('port', 8000);
        $publicDir = $this->hasOption('public') ? (string) $this->option('public') : public_path();

        if (! is_dir($publicDir)) {
            $this->error("Document root directory [{$publicDir}] does not exist.");

            return 1;
        }

        $phpBinary = escapeshellarg(PHP_BINARY ?: 'php');
        $escapedRoot = escapeshellarg($publicDir);

        $this->info("ZF1 development server started on http://{$host}:{$port}");
        $this->comment("  Document root: {$publicDir}");
        $this->comment('  Press Ctrl+C to stop the server.');
        echo PHP_EOL;

        // Run built-in server with passthru
        $cmd = "{$phpBinary} -S {$host}:{$port} -t {$escapedRoot}";
        passthru($cmd, $exitCode);

        return $exitCode;
    }
}
