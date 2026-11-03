<?php

declare(strict_types=1);

namespace ZF1\Console;

use ReflectionClass;
use Throwable;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Cli;

/**
 * Modern Console Application runner for ZF1.
 */
final class Application
{
    /**
     * Map of registered command instances keyed by command name and alias.
     *
     * @var array<string, Command>
     */
    protected static array $commands = [];

    /**
     * Unique registered command instances.
     *
     * @var array<string, Command>
     */
    protected static array $uniqueCommands = [];

    /**
     * Whether default and application commands have been discovered.
     */
    protected static bool $booted = false;

    /**
     * Run the console application with the given arguments.
     *
     * @param  array<string>  $args  $argv without script name
     * @return int Exit code
     */
    public static function run(array $args): int
    {
        self::boot();

        if (empty($args) || (count($args) === 1 && in_array($args[0], ['-h', '--help', 'help', 'list'], true))) {
            self::showHelp();

            return 0;
        }

        // Support: php zf help <command>
        if ($args[0] === 'help' && isset($args[1])) {
            $target = $args[1];
            if (isset(self::$commands[$target])) {
                self::$commands[$target]->showHelp();

                return 0;
            }
        }

        $commandName = $args[0];

        // 1. Check if command matches a registered Command object
        if (isset(self::$commands[$commandName])) {
            $command = self::$commands[$commandName];
            $subArgs = array_slice($args, 1);

            // Support: php zf <command> -h or --help
            if (in_array('-h', $subArgs, true) || in_array('--help', $subArgs, true)) {
                $command->showHelp();

                return 0;
            }

            $parsed = self::parseInput($subArgs);
            $command->bindInput($parsed['arguments'], $parsed['options']);

            return $command->handle();
        }

        // 2. Fallback to traditional Zend MVC Controller / Action dispatch
        return self::handleMvcDispatch($args);
    }

    /**
     * Register built-in Core commands, application commands, and module commands.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        // 1. Register Built-in Core Commands
        self::registerBuiltInCommands();

        // 2. Discover Application Commands: application/Commands/
        self::discoverCommandsInDirectory(app_path('Commands'));

        // 3. Discover Module Commands: application/modules/*/Commands/
        $modulesPath = app_path('modules');
        if (is_dir($modulesPath)) {
            foreach (glob($modulesPath.'/*/Commands', GLOB_ONLYDIR) as $moduleCommandDir) {
                self::discoverCommandsInDirectory($moduleCommandDir);
            }
        }

        self::$booted = true;
    }

    /**
     * Register a Command instance or class string.
     */
    public static function register(Command|string $command): void
    {
        if (is_string($command)) {
            if (! class_exists($command)) {
                return;
            }
            $command = new $command;
        }

        if (! ($command instanceof Command)) {
            return;
        }

        $name = $command->getName();
        if ($name === '') {
            return;
        }

        self::$commands[$name] = $command;
        self::$uniqueCommands[$name] = $command;

        foreach ($command->getAliases() as $alias) {
            self::$commands[$alias] = $command;
        }
    }

    /**
     * Register standard built-in framework commands.
     */
    protected static function registerBuiltInCommands(): void
    {
        $coreCommands = [
            Commands\Queue\TableCommand::class,
            Commands\Queue\WorkCommand::class,
            Commands\Queue\StatusCommand::class,
            Commands\Queue\FailedCommand::class,
            Commands\Queue\RetryCommand::class,
            Commands\Queue\ForgetCommand::class,
            Commands\Queue\FlushCommand::class,
            Commands\Queue\PruneCommand::class,
            Commands\Schedule\RunCommand::class,
            Commands\Schedule\ListCommand::class,
            Commands\Schedule\WorkCommand::class,
            Commands\System\DownCommand::class,
            Commands\System\UpCommand::class,
            Commands\System\BackupCommand::class,
            Commands\System\CacheClearCommand::class,
            Commands\System\FoundationCommand::class,
            Commands\System\MakeCommandCommand::class,
            Commands\System\MakeJobCommand::class,
            Commands\System\ServeCommand::class,
            Commands\System\AboutCommand::class,
            Commands\System\RouteListCommand::class,
        ];

        foreach ($coreCommands as $class) {
            if (class_exists($class)) {
                self::register($class);
            }
        }
    }

    /**
     * Scan a directory recursively for classes extending ZF1\Console\Command.
     */
    public static function discoverCommandsInDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.'/*.php');
        if (! empty($files)) {
            foreach ($files as $file) {
                self::loadCommandFromFile($file);
            }
        }

        // Subdirectories
        foreach (glob($directory.'/*', GLOB_ONLYDIR) as $subDir) {
            self::discoverCommandsInDirectory($subDir);
        }
    }

    /**
     * Inspect a PHP file and register command if it defines a Command subclass.
     */
    protected static function loadCommandFromFile(string $filePath): void
    {
        $declaredBefore = get_declared_classes();
        require_once $filePath;
        $declaredAfter = get_declared_classes();

        $newClasses = array_diff($declaredAfter, $declaredBefore);

        foreach ($newClasses as $class) {
            try {
                $ref = new ReflectionClass($class);
                if (! $ref->isAbstract() && $ref->isSubclassOf(Command::class)) {
                    self::register($ref->newInstance());
                }
            } catch (Throwable) {
                // Ignore classes that cannot be reflected or instantiated
            }
        }
    }

    /**
     * Parse CLI arguments into positional arguments and named options.
     *
     * @param  array<string>  $rawArgs
     * @return array{arguments: array<string>, options: array<string, mixed>}
     */
    protected static function parseInput(array $rawArgs): array
    {
        $arguments = [];
        $options = [];

        foreach ($rawArgs as $arg) {
            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$k, $v] = explode('=', $option, 2);
                    $options[$k] = $v;
                } else {
                    $options[$option] = true;
                }
            } elseif (str_contains($arg, '=')) {
                [$k, $v] = explode('=', $arg, 2);
                $options[$k] = $v;
            } else {
                $arguments[] = $arg;
            }
        }

        return [
            'arguments' => $arguments,
            'options' => $options,
        ];
    }

    /**
     * Fallback to Zend Controller Front dispatch for traditional CLI requests.
     */
    protected static function handleMvcDispatch(array $args): int
    {
        $controller = $args[0];
        $action = $args[1] ?? 'index';

        $parsed = self::parseInput(array_slice($args, 2));

        foreach ($parsed['options'] as $k => $v) {
            $_GET[$k] = $v;
            $_REQUEST[$k] = $v;
        }

        $front = Zend_Controller_Front::getInstance();
        $request = new Zend_Controller_Request_Http;
        $request->setRequestUri("/{$controller}/{$action}");
        $request->setControllerName($controller);
        $request->setActionName($action);

        foreach ($parsed['options'] as $k => $v) {
            $request->setParam($k, $v);
        }

        $response = new Zend_Controller_Response_Cli;

        $front->setRequest($request)
            ->setResponse($response)
            ->returnResponse(true)
            ->throwExceptions(true);

        try {
            $front->dispatch();
            $body = $response->getBody();
            if ($body !== '') {
                echo $body.PHP_EOL;
            }

            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, 'Error: '.$e->getMessage().PHP_EOL);

            return 1;
        }
    }

    /**
     * Display dynamic, grouped console help screen.
     */
    public static function showHelp(): void
    {
        $appName = (string) env('APP_NAME', 'ZF1');

        echo PHP_EOL."\033[32m{$appName} Console\033[0m".PHP_EOL.PHP_EOL;
        echo "\033[33mUsage:\033[0m".PHP_EOL;
        echo '  command [arguments] [options]'.PHP_EOL;
        echo '  <controller> <action> [--param=val] (Zend MVC routing)'.PHP_EOL.PHP_EOL;

        echo "\033[33mAvailable commands:\033[0m".PHP_EOL;

        // Group commands by namespace (e.g. 'queue', 'schedule', 'db', or root)
        $groups = [];
        foreach (self::$uniqueCommands as $name => $cmd) {
            $parts = explode(':', $name, 2);
            $group = count($parts) === 2 ? $parts[0] : '';
            $groups[$group][$name] = $cmd;
        }

        // Sort groups alphabetically with root/global commands first
        uksort($groups, static function ($a, $b) {
            if ($a === '') {
                return -1;
            }
            if ($b === '') {
                return 1;
            }

            return strcmp($a, $b);
        });

        foreach ($groups as $group => $commands) {
            if ($group !== '') {
                echo " \033[33m{$group}\033[0m".PHP_EOL;
            }

            ksort($commands);

            foreach ($commands as $name => $cmd) {
                $synopsis = $cmd->getSynopsis();
                $displayName = $name.($synopsis !== '' ? " {$synopsis}" : '');
                $aliases = $cmd->getAliases();
                $aliasStr = ! empty($aliases) ? ' ('.implode(', ', $aliases).')' : '';
                printf("  \033[32m%-38s\033[0m %s%s\n", $displayName, $cmd->getDescription(), $aliasStr);
            }
        }

        echo PHP_EOL;
    }
}
