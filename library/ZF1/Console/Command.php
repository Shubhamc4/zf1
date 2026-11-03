<?php

declare(strict_types=1);

namespace ZF1\Console;

use RuntimeException;

abstract class Command
{
    /**
     * The name and signature of the console command.
     * Supports Laravel-style syntax:
     *   "db:backup {--days=30 : Days to retain} {--force : Force backup}"
     *   "queue:retry {id=all : Job ID or all}"
     *   "db:backup :days"
     */
    protected string $signature = '';

    /**
     * The console command name (used if $signature is empty).
     */
    protected string $name = '';

    /**
     * The alternative aliases for the command.
     *
     * @var array<string>
     */
    protected array $aliases = [];

    /**
     * The console command description.
     */
    protected string $description = '';

    /**
     * Parsed options (e.g. ['queue' => 'default', 'force' => true]).
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * Raw and named positional arguments.
     *
     * @var array<string|int, mixed>
     */
    protected array $arguments = [];

    /**
     * Defined argument metadata from signature.
     *
     * @var array<string, array{name: string, required: bool, default: mixed, description: string}>
     */
    protected array $definedArguments = [];

    /**
     * Defined option metadata from signature.
     *
     * @var array<string, array{name: string, shortcut: ?string, hasValue: bool, default: mixed, description: string}>
     */
    protected array $definedOptions = [];

    /**
     * Whether the signature has been parsed.
     */
    private bool $signatureParsed = false;

    /**
     * Execute the console command.
     *
     * @return int Exit code (0 for success, non-zero for error)
     */
    abstract public function handle(): int;

    /**
     * Get the command name.
     */
    public function getName(): string
    {
        $this->parseSignature();

        return $this->name;
    }

    /**
     * Get the command signature.
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Get command aliases.
     *
     * @return array<string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get a compact synopsis of parameters for listing.
     */
    public function getSynopsis(): string
    {
        $this->parseSignature();

        $parts = [];
        foreach ($this->definedArguments as $arg) {
            $parts[] = $arg['required'] ? "<{$arg['name']}>" : "[{$arg['name']}]";
        }
        foreach ($this->definedOptions as $opt) {
            if ($opt['hasValue']) {
                $valStr = $opt['default'] !== null ? "={$opt['default']}" : '';
                $parts[] = "{--{$opt['name']}{$valStr}}";
            } else {
                $parts[] = "{--{$opt['name']}}";
            }
        }

        return ! empty($parts) ? implode(' ', $parts) : '';
    }

    /**
     * Parse the command signature into defined arguments and options.
     */
    public function parseSignature(): void
    {
        if ($this->signatureParsed) {
            return;
        }

        $this->signatureParsed = true;
        $sig = trim($this->signature);

        if ($sig === '') {
            return;
        }

        // 1. Extract command name (everything before whitespace or '{')
        if (preg_match('/^([^\s{]+)/', $sig, $nameMatch)) {
            if ($this->name === '') {
                $this->name = trim($nameMatch[1]);
            }
        }

        // 2. Parse Laravel-style tokens in { ... }
        if (preg_match_all('/\{([^}]+)\}/', $sig, $matches)) {
            foreach ($matches[1] as $token) {
                $token = trim($token);
                $description = '';

                if (str_contains($token, ' : ')) {
                    [$token, $description] = explode(' : ', $token, 2);
                    $token = trim($token);
                    $description = trim($description);
                }

                if (str_starts_with($token, '--')) {
                    // Option definition
                    $token = substr($token, 2);
                    $shortcut = null;

                    if (str_contains($token, '|')) {
                        [$shortcut, $token] = explode('|', $token, 2);
                        $shortcut = trim($shortcut);
                        $token = trim($token);
                    }

                    $hasValue = false;
                    $default = false;

                    if (str_contains($token, '=')) {
                        [$optName, $defaultVal] = explode('=', $token, 2);
                        $optName = trim($optName);
                        $hasValue = true;
                        $default = $defaultVal !== '' ? $defaultVal : null;
                    } else {
                        $optName = trim($token);
                    }

                    $this->definedOptions[$optName] = [
                        'name' => $optName,
                        'shortcut' => $shortcut,
                        'hasValue' => $hasValue,
                        'default' => $default,
                        'description' => $description,
                    ];
                } else {
                    // Argument definition
                    $required = true;
                    $default = null;

                    if (str_ends_with($token, '?')) {
                        $argName = substr($token, 0, -1);
                        $required = false;
                    } elseif (str_contains($token, '=')) {
                        [$argName, $defaultVal] = explode('=', $token, 2);
                        $argName = trim($argName);
                        $required = false;
                        $default = $defaultVal;
                    } else {
                        $argName = trim($token);
                    }

                    $this->definedArguments[$argName] = [
                        'name' => $argName,
                        'required' => $required,
                        'default' => $default,
                        'description' => $description,
                    ];
                }
            }
        }

        // 3. Shorthand support: e.g. "db:backup :days" or "db:backup :days=30"
        $afterName = trim(substr($sig, strlen($this->name)));
        if ($afterName !== '' && preg_match_all('/:([a-zA-Z0-9_-]+)(?:=([^\s]+))?/', $afterName, $shortMatches, PREG_SET_ORDER)) {
            foreach ($shortMatches as $sm) {
                $argName = $sm[1];
                $default = $sm[2] ?? null;
                if (! isset($this->definedArguments[$argName])) {
                    $this->definedArguments[$argName] = [
                        'name' => $argName,
                        'required' => $default === null,
                        'default' => $default,
                        'description' => '',
                    ];
                }
            }
        }
    }

    /**
     * Bind input arguments and options and fill defaults from defined parameters.
     *
     * @param  array<int, string>  $rawArguments
     * @param  array<string, mixed>  $rawOptions
     */
    public function bindInput(array $rawArguments, array $rawOptions): static
    {
        $this->parseSignature();

        // 1. Map positional arguments to defined named arguments
        $this->arguments = $rawArguments;
        $argNames = array_keys($this->definedArguments);

        foreach ($argNames as $idx => $argName) {
            if (isset($rawArguments[$idx])) {
                $this->arguments[$argName] = $rawArguments[$idx];
            } elseif ($this->definedArguments[$argName]['default'] !== null) {
                $this->arguments[$argName] = $this->definedArguments[$argName]['default'];
            }
        }

        // 2. Map options and fill defaults from signature
        $this->options = $rawOptions;

        foreach ($this->definedOptions as $optName => $meta) {
            // Check shortcut
            if ($meta['shortcut'] !== null && isset($rawOptions[$meta['shortcut']]) && ! isset($this->options[$optName])) {
                $this->options[$optName] = $rawOptions[$meta['shortcut']];
            }

            // Fill default if not supplied
            if (! array_key_exists($optName, $this->options)) {
                $this->options[$optName] = $meta['default'];
            }
        }

        return $this;
    }

    /**
     * Get a positional or named argument.
     */
    public function argument(string|int|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->arguments;
        }

        if (array_key_exists($key, $this->arguments)) {
            return $this->arguments[$key];
        }

        if (is_string($key) && isset($this->definedArguments[$key])) {
            return $this->definedArguments[$key]['default'] ?? $default;
        }

        return $default;
    }

    /**
     * Check if an argument was supplied.
     */
    public function hasArgument(string|int $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    /**
     * Get an option by name with default fallback.
     */
    public function option(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        if (isset($this->definedOptions[$name])) {
            return $this->definedOptions[$name]['default'] ?? $default;
        }

        return $default;
    }

    /**
     * Check if an option was supplied (or is true).
     */
    public function hasOption(string $name): bool
    {
        return ! empty($this->options[$name]);
    }

    /**
     * Get all parsed options.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Set the raw arguments for this execution.
     *
     * @param  array<string|int, mixed>  $arguments
     */
    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Set the parsed options for this execution.
     *
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Display detailed help for this specific command (-h or --help).
     */
    public function showHelp(): void
    {
        $this->parseSignature();

        $name = $this->getName();
        $desc = $this->getDescription();

        echo PHP_EOL."\033[33mDescription:\033[0m".PHP_EOL;
        echo '  '.($desc !== '' ? $desc : 'No description provided.').PHP_EOL.PHP_EOL;

        echo "\033[33mUsage:\033[0m".PHP_EOL;
        $synopsis = $this->getSynopsis();
        $cliName = ! empty($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : 'zf';
        echo "  php {$cliName} {$name}".($synopsis !== '' ? " {$synopsis}" : '').PHP_EOL.PHP_EOL;

        if (! empty($this->aliases)) {
            echo "\033[33mAliases:\033[0m".PHP_EOL;
            echo '  '.implode(', ', $this->aliases).PHP_EOL.PHP_EOL;
        }

        if (! empty($this->definedArguments)) {
            echo "\033[33mArguments:\033[0m".PHP_EOL;
            foreach ($this->definedArguments as $arg) {
                $req = $arg['required'] ? "\033[31m[required]\033[0m" : ($arg['default'] !== null ? "[default: \"{$arg['default']}\"]" : '[optional]');
                $desc = $arg['description'] !== '' ? $arg['description'] : '';
                printf("  \033[32m%-22s\033[0m %s %s\n", $arg['name'], $desc, $req);
            }
            echo PHP_EOL;
        }

        echo "\033[33mOptions:\033[0m".PHP_EOL;
        if (! empty($this->definedOptions)) {
            foreach ($this->definedOptions as $opt) {
                $flag = '--'.$opt['name'];
                if ($opt['shortcut'] !== null) {
                    $flag = "-{$opt['shortcut']}, {$flag}";
                }
                if ($opt['hasValue']) {
                    $flag .= '=VALUE';
                }
                $def = $opt['default'] !== false && $opt['default'] !== null ? " [default: \"{$opt['default']}\"]" : '';
                printf("  \033[32m%-26s\033[0m %s%s\n", $flag, $opt['description'], $def);
            }
        }
        printf("  \033[32m%-26s\033[0m %s\n", '-h, --help', 'Display help for the given command');
        echo PHP_EOL;
    }

    /**
     * Write standard text to console.
     */
    public function line(string $message): void
    {
        echo $message.PHP_EOL;
    }

    /**
     * Write info text (green).
     */
    public function info(string $message): void
    {
        echo "\033[32m{$message}\033[0m".PHP_EOL;
    }

    /**
     * Write comment / notice text (yellow).
     */
    public function comment(string $message): void
    {
        echo "\033[33m{$message}\033[0m".PHP_EOL;
    }

    /**
     * Write warning text (yellow bold).
     */
    public function warn(string $message): void
    {
        echo "\033[1;33m{$message}\033[0m".PHP_EOL;
    }

    /**
     * Write error text (red).
     */
    public function error(string $message): void
    {
        echo "\033[31m{$message}\033[0m".PHP_EOL;
    }

    /**
     * Render a formatted ASCII table.
     *
     * @param  array<string>  $headers
     * @param  array<array<string|int|float>>  $rows
     */
    public function table(array $headers, array $rows): void
    {
        if (empty($headers) && empty($rows)) {
            return;
        }

        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = mb_strwidth((string) $header);
        }

        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $width = mb_strwidth((string) $cell);
                if (! isset($colWidths[$i]) || $width > $colWidths[$i]) {
                    $colWidths[$i] = $width;
                }
            }
        }

        $separator = '+';
        foreach ($colWidths as $w) {
            $separator .= str_repeat('-', $w + 2).'+';
        }

        $renderRow = static function (array $cells) use ($colWidths): string {
            $line = '|';
            foreach (array_values($cells) as $i => $cell) {
                $w = $colWidths[$i] ?? 10;
                $line .= ' '.str_pad((string) $cell, $w).' |';
            }

            return $line;
        };

        echo $separator.PHP_EOL;
        echo $renderRow($headers).PHP_EOL;
        echo $separator.PHP_EOL;
        foreach ($rows as $row) {
            echo $renderRow($row).PHP_EOL;
        }
        echo $separator.PHP_EOL;
    }

    /**
     * Resolve a stub template, checking first in <project-root>/stubs/ for project overrides.
     *
     * @param  string  $name  Stub file name (e.g. 'command.stub', 'job.stub', 'zf.stub')
     * @return string Raw stub template contents
     */
    public static function resolveStub(string $name): string
    {
        $customPath = base_path("stubs/{$name}");
        if (is_file($customPath) && is_readable($customPath)) {
            return (string) file_get_contents($customPath);
        }

        $frameworkStub = __DIR__.'/stubs/'.$name;
        if (is_file($frameworkStub) && is_readable($frameworkStub)) {
            return (string) file_get_contents($frameworkStub);
        }

        throw new RuntimeException("Framework stub template [{$name}] not found at [{$frameworkStub}].");
    }
}
