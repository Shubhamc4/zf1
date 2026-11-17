<?php

declare(strict_types=1);

namespace ZF1\Console;

use ReflectionClass;
use Throwable;
use Zend_Controller_Dispatcher_Exception;
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

        if (str_contains($commandName, ':')) {
            return self::renderCommandNotFound($commandName);
        }

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

        self::registerBuiltInCommands();
        self::discoverCommandsInDirectory(app_path('Commands'));
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
     * Get all registered unique command instances keyed by command name.
     *
     * @return array<string, Command>
     */
    public static function getUniqueCommands(): array
    {
        self::boot();

        return self::$uniqueCommands;
    }

    /**
     * Get all registered command instances keyed by name and alias.
     *
     * @return array<string, Command>
     */
    public static function getCommands(): array
    {
        self::boot();

        return self::$commands;
    }

    /**
     * Get a registered command by name or alias.
     */
    public static function getCommand(string $name): ?Command
    {
        self::boot();

        return self::$commands[$name] ?? null;
    }

    /**
     * Check if a command is registered.
     */
    public static function hasCommand(string $name): bool
    {
        self::boot();

        return isset(self::$commands[$name]);
    }

    /**
     * Register standard built-in framework commands dynamically from Commands/ directory and subdirectories.
     */
    protected static function registerBuiltInCommands(): void
    {
        $builtInPath = __DIR__.'/Commands';
        if (is_dir($builtInPath)) {
            self::discoverCommandsInDirectory($builtInPath);
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

        $candidateClasses = array_diff($declaredAfter, $declaredBefore);

        $fileClass = self::getClassFromFile($filePath);
        if ($fileClass !== null && ! in_array($fileClass, $candidateClasses, true)) {
            $candidateClasses[] = $fileClass;
        }

        foreach ($candidateClasses as $class) {
            try {
                if (class_exists($class, false)) {
                    $ref = new ReflectionClass($class);
                    if (! $ref->isAbstract() && $ref->isSubclassOf(Command::class)) {
                        self::register($ref->newInstance());
                    }
                }
            } catch (Throwable) {
                // Ignore classes that cannot be reflected or instantiated
            }
        }
    }

    /**
     * Extract fully qualified class name from a PHP file.
     */
    protected static function getClassFromFile(string $filePath): ?string
    {
        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return null;
        }

        $tokens = @token_get_all($content);
        $namespace = '';
        $class = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i += 2;
                while ($i < $count && (
                    $tokens[$i][0] === T_STRING
                    || $tokens[$i][0] === T_NS_SEPARATOR
                    || (defined('T_NAME_QUALIFIED') && $tokens[$i][0] === T_NAME_QUALIFIED)
                )) {
                    $namespace .= $tokens[$i][1];
                    $i++;
                }
            } elseif ($tokens[$i][0] === T_CLASS) {
                $prev = $i - 1;
                while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                    $prev--;
                }
                if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_DOUBLE_COLON) {
                    continue;
                }

                $next = $i + 1;
                while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
                    $next++;
                }
                if ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                    $class = $tokens[$next][1];
                    break;
                }
            }
        }

        if ($class === '') {
            return null;
        }

        return $namespace !== '' ? "{$namespace}\\{$class}" : $class;
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
            if ($e instanceof Zend_Controller_Dispatcher_Exception) {
                return self::renderCommandNotFound($controller);
            }

            echo "\033[31mError: ".$e->getMessage()."\033[0m".PHP_EOL;

            return 1;
        }
    }

    /**
     * Render a standardized command not found error with suggestions.
     */
    public static function renderCommandNotFound(string $name): int
    {
        echo PHP_EOL."\033[31mCommand \"{$name}\" is not defined.\033[0m".PHP_EOL.PHP_EOL;

        $alternatives = self::findAlternatives($name);
        if (! empty($alternatives)) {
            echo "\033[33mDid you mean one of these?\033[0m".PHP_EOL;
            foreach ($alternatives as $alt) {
                echo "  \033[32m{$alt}\033[0m".PHP_EOL;
            }
            echo PHP_EOL;
        }

        return 1;
    }

    /**
     * Find registered commands similar to the given command name.
     *
     * @return array<string>
     */
    protected static function findAlternatives(string $name): array
    {
        $allNames = array_keys(self::$uniqueCommands);
        $threshold = 4;
        $alternatives = [];

        foreach ($allNames as $candidate) {
            $lev = levenshtein($name, $candidate);
            if ($lev <= $threshold) {
                $alternatives[$candidate] = $lev;
            } elseif (str_contains($candidate, $name) || str_contains($name, $candidate)) {
                $alternatives[$candidate] = 10;
            } else {
                $nameParts = explode(':', $name, 2);
                $candParts = explode(':', $candidate, 2);
                if (count($nameParts) === 2 && count($candParts) === 2) {
                    if ($nameParts[0] === $candParts[0] || $nameParts[1] === $candParts[1]) {
                        $alternatives[$candidate] = 15;
                    }
                }
            }
        }

        asort($alternatives);

        return array_slice(array_keys($alternatives), 0, 3);
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

        $groups = [];
        foreach (self::$uniqueCommands as $name => $cmd) {
            $parts = explode(':', $name, 2);
            $group = count($parts) === 2 ? $parts[0] : '';
            $groups[$group][$name] = $cmd;
        }

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
