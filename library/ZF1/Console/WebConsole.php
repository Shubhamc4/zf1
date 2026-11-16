<?php

declare(strict_types=1);

namespace ZF1\Console;

use AllowDynamicProperties;
use Closure;
use ReflectionClass;
use RuntimeException;
use Throwable;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;
use Zend_Layout;
use Zend_View;
use ZF1\Foundation\Kernel;

/**
 * Web Terminal & Console Runner for Zend Framework 1.
 *
 * @method $this path(string $path) Set the web console URL path.
 * @method $this enable(bool $enabled = true) Enable or disable the Web Console.
 * @method $this allowDuringMaintenance(bool $allow = true) Configure accessibility during maintenance mode.
 * @method $this allowedIps(array<string>|string $ips) Set or append whitelisted client IP addresses.
 * @method $this onlyProjectCommands(bool $only = true) Filter list to only application-level commands.
 * @method $this hideCommands(array<string> $commands) Hide sensitive commands from execution and listing.
 * @method $this priorityGroups(array<string> $groups) Set priority order of command groups in sidebar.
 * @method $this title(string $title) Set custom web console browser title and header.
 * @method $this homeUrl(string $url) Set header home link target URL.
 * @method $this theme(string $theme) Set default color theme ('dark', 'light', or 'auto').
 * @method $this customCss(string $css) Inject custom CSS styles into the web console view.
 * @method $this auth(callable $gate) Register authorization gate closure.
 * @method $this redirectOnForbidden(string $url) Set redirect URL when access is denied.
 * @method $this view(string $path) Set explicit custom view template path.
 */
final class WebConsole
{
    /**
     * Explicit enable override.
     */
    protected static ?bool $enabled = null;

    /**
     * Web console base endpoint path (e.g. '/zf-console').
     */
    protected static string $path = '/zf-console';

    /**
     * Whether Web Console can be accessed while the application is in maintenance mode.
     */
    protected static bool $allowDuringMaintenance = true;

    /**
     * Whitelisted client IP addresses / CIDRs.
     *
     * @var array<string>
     */
    protected static array $allowedIps = [];

    /**
     * Whether to show only application-defined commands (application/Commands/).
     * Defaults to false (shows all commands including core framework commands).
     */
    protected static bool $onlyProjectCommands = false;

    /**
     * Blacklisted commands that cannot be viewed or executed via the web console.
     *
     * @var array<string>
     */
    protected static array $hiddenCommands = [];

    /**
     * Custom priority ordering for command group display in sidebar.
     *
     * @var array<string>
     */
    protected static array $priorityGroups = [
        'app',
        'general',
        'csr',
        'dispatch',
        'vsr',
        'queue',
        'schedule',
        'system',
        'db',
        'cache',
        'mail',
        'make',
        'route',
    ];

    /**
     * Custom browser window and console title.
     */
    protected static ?string $title = null;

    /**
     * Custom home URL link for the brand header.
     */
    protected static ?string $homeUrl = null;

    /**
     * Default color theme ('dark', 'light', or 'auto').
     */
    protected static string $theme = 'dark';

    /**
     * Additional CSS to inject into the web console page.
     */
    protected static string $customCss = '';

    /**
     * Custom authorization gate callback.
     */
    protected static ?Closure $authGate = null;

    /**
     * Optional redirect URL when authorization fails.
     */
    protected static ?string $redirectOnForbidden = null;

    /**
     * Explicit custom view template path override.
     */
    protected static ?string $customView = null;

    /**
     * Start a fluent configuration builder chain.
     */
    public static function configure(): static
    {
        return new self;
    }

    /**
     * Set or get the web console URL path.
     *
     *
     * @phpstan-return ($path is null ? string : static)
     */
    public static function path(?string $path = null): static|string
    {
        if ($path === null) {
            $configured = (string) env('ZF_CONSOLE_PATH', env('CONSOLE_PATH', self::$path));

            return '/'.trim($configured, '/');
        }

        self::$path = '/'.trim($path, '/');

        return new self;
    }

    /**
     * Enable or disable the Web Console.
     */
    public static function enable(bool $enabled = true): static
    {
        self::$enabled = $enabled;

        return new self;
    }

    /**
     * Check if Web Console is enabled in the current environment.
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        $envVal = env('ZF_CONSOLE_ENABLED', env('CONSOLE_ENABLED'));
        if ($envVal !== null) {
            return filter_var($envVal, FILTER_VALIDATE_BOOLEAN);
        }

        // By default, if application is in maintenance mode and allowed during maintenance, enable console
        if (self::isAllowedDuringMaintenance()
            && class_exists(\ZF1\System\MaintenanceMode::class)
            && \ZF1\System\MaintenanceMode::isDown()
        ) {
            return true;
        }

        // By default, enabled in non-production environments
        if (function_exists('is_production')) {
            return ! is_production();
        }

        return defined('APPLICATION_ENV') && APPLICATION_ENV !== 'production';
    }

    /**
     * Configure whether Web Console is accessible while the application is in maintenance mode.
     */
    public static function allowDuringMaintenance(bool $allow = true): static
    {
        self::$allowDuringMaintenance = $allow;

        return new self;
    }

    /**
     * Check if Web Console is allowed during maintenance mode.
     */
    public static function isAllowedDuringMaintenance(): bool
    {
        $envVal = env('ZF_CONSOLE_ALLOW_MAINTENANCE', env('CONSOLE_ALLOW_MAINTENANCE'));
        if ($envVal !== null) {
            return filter_var($envVal, FILTER_VALIDATE_BOOLEAN);
        }

        return self::$allowDuringMaintenance;
    }

    /**
     * Set or append whitelisted client IP addresses.
     *
     * @param  array<string>|string  $ips
     */
    public static function allowedIps(array|string $ips): static
    {
        $list = is_array($ips) ? $ips : explode(',', $ips);
        foreach ($list as $ip) {
            $ip = trim((string) $ip);
            if ($ip !== '' && ! in_array($ip, self::$allowedIps, true)) {
                self::$allowedIps[] = $ip;
            }
        }

        return new self;
    }

    /**
     * Check if the current client IP address is permitted.
     */
    public static function checkIp(?string $clientIp = null): bool
    {
        $configuredEnv = (string) env('ZF_CONSOLE_ALLOWED_IPS', '');
        $allowed = self::$allowedIps;

        if ($configuredEnv !== '') {
            $fromEnv = array_filter(array_map('trim', explode(',', $configuredEnv)));
            $allowed = array_unique(array_merge($allowed, $fromEnv));
        }

        if (empty($allowed)) {
            return true;
        }

        $ip = $clientIp ?: (function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

        foreach ($allowed as $pattern) {
            if ($pattern === '*' || $pattern === $ip) {
                return true;
            }

            // Wildcard matching (e.g. 192.168.1.*)
            if (str_contains($pattern, '*')) {
                $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#';
                if (preg_match($regex, $ip)) {
                    return true;
                }
            }

            // CIDR matching (e.g. 10.0.0.0/8)
            if (str_contains($pattern, '/') && self::ipInCidr($ip, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configure whether to only show application-defined commands (application/Commands/).
     */
    public static function onlyProjectCommands(bool $only = true): static
    {
        self::$onlyProjectCommands = $only;

        return new self;
    }

    /**
     * Check whether only application commands should be displayed.
     */
    public static function isOnlyProjectCommands(): bool
    {
        return self::$onlyProjectCommands;
    }

    /**
     * Specify commands that should not appear in the web UI and cannot be executed via web.
     *
     * @param  array<string>  $commands
     */
    public static function hideCommands(array $commands): static
    {
        foreach ($commands as $cmd) {
            $cmd = trim((string) $cmd);
            if ($cmd !== '' && ! in_array($cmd, self::$hiddenCommands, true)) {
                self::$hiddenCommands[] = $cmd;
            }
        }

        return new self;
    }

    /**
     * Set the priority order of command groups displayed in the sidebar.
     *
     * @param  array<string>  $groups
     */
    public static function priorityGroups(array $groups): static
    {
        self::$priorityGroups = array_values(array_unique(array_map('strtolower', $groups)));

        return new self;
    }

    /**
     * Set or get the custom title.
     *
     *
     * @phpstan-return ($title is null ? string : static)
     */
    public static function title(?string $title = null): static|string
    {
        if ($title === null) {
            return self::$title ?? (defined('APP_NAME') ? APP_NAME : (string) env('APP_NAME', 'ZF1'));
        }

        self::$title = $title;

        return new self;
    }

    /**
     * Set or get the home link URL.
     *
     *
     * @phpstan-return ($url is null ? string : static)
     */
    public static function homeUrl(?string $url = null): static|string
    {
        if ($url === null) {
            return self::$homeUrl ?? (function_exists('url') ? url('/') : '/');
        }

        self::$homeUrl = $url;

        return new self;
    }

    /**
     * Set or get default theme ('dark', 'light', or 'auto').
     *
     *
     * @phpstan-return ($theme is null ? string : static)
     */
    public static function theme(?string $theme = null): static|string
    {
        if ($theme === null) {
            return self::$theme;
        }

        self::$theme = in_array(strtolower($theme), ['dark', 'light', 'auto'], true) ? strtolower($theme) : 'dark';

        return new self;
    }

    /**
     * Inject custom CSS styles into the web console.
     */
    public static function customCss(string $css): static
    {
        self::$customCss = $css;

        return new self;
    }

    /**
     * Register an authorization gate closure for web console access.
     *
     * Closure signature: function (Zend_Controller_Request_Http $request, ?Bootstrap $bootstrap): bool
     */
    public static function auth(callable $gate): static
    {
        self::$authGate = $gate instanceof Closure ? $gate : Closure::fromCallable($gate);

        return new self;
    }

    /**
     * Get the registered auth gate.
     */
    public static function getAuthGate(): ?Closure
    {
        return self::$authGate;
    }

    /**
     * Set redirect URL when access is denied.
     *
     *
     * @phpstan-return ($url is null ? string|null : static)
     */
    public static function redirectOnForbidden(?string $url = null): static|string|null
    {
        if ($url === null) {
            return self::$redirectOnForbidden;
        }

        self::$redirectOnForbidden = $url;

        return new self;
    }

    /**
     * Resolve target redirect URL when access is denied to show project setup views and error management.
     */
    public static function resolveForbiddenRedirect(): string
    {
        if (self::$redirectOnForbidden !== null && self::$redirectOnForbidden !== '') {
            return self::$redirectOnForbidden;
        }

        $envRedirect = (string) env('ZF_CONSOLE_REDIRECT_ON_FORBIDDEN', '');
        if ($envRedirect !== '') {
            return $envRedirect;
        }

        if (class_exists('ErrorController', false) || (function_exists('app_path') && file_exists(app_path('controllers/ErrorController.php')))) {
            if (class_exists('ErrorController') || (function_exists('app_path') && @include_once app_path('controllers/ErrorController.php'))) {
                if (method_exists('ErrorController', 'unauthorizedAction')) {
                    return '/error/unauthorized';
                }
                if (method_exists('ErrorController', 'forbiddenAction')) {
                    return '/error/forbidden';
                }
            }

            return '/error';
        }

        return '/error';
    }

    /**
     * Set explicit custom view template path.
     *
     *
     * @phpstan-return ($path is null ? string|null : static)
     */
    public static function view(?string $path = null): static|string|null
    {
        if ($path === null) {
            return self::$customView;
        }

        self::$customView = $path;

        return new self;
    }

    /**
     * Check if a given URI path matches the Web Console route.
     */
    public static function matchesPath(string $uri): bool
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $cleanUri = '/'.trim($path, '/');

        if (str_starts_with($cleanUri, '/index.php/')) {
            $cleanUri = substr($cleanUri, 10);
        } elseif ($cleanUri === '/index.php') {
            $cleanUri = '/';
        }

        $consolePath = self::path();

        return $cleanUri === $consolePath
            || str_starts_with($cleanUri, $consolePath.'/');
    }

    /**
     * Check if a given URI is an execute request.
     */
    public static function isExecuteRequest(string $uri): bool
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $cleanUri = '/'.trim($path, '/');

        if (str_starts_with($cleanUri, '/index.php/')) {
            $cleanUri = substr($cleanUri, 10);
        } elseif ($cleanUri === '/index.php') {
            $cleanUri = '/';
        }

        $executeTarget = rtrim(self::path(), '/').'/execute';

        return $cleanUri === $executeTarget;
    }

    /**
     * Check authorization against registered gate or Bootstrap hook.
     */
    public static function checkAuth(mixed $request = null, mixed $bootstrap = null): bool
    {
        if (self::$authGate !== null) {
            return (bool) call_user_func(self::$authGate, $request, $bootstrap);
        }

        if ($bootstrap !== null && method_exists($bootstrap, 'authorizeWebConsole')) {
            return (bool) $bootstrap->authorizeWebConsole($request);
        }

        $app = Kernel::getApplication();
        if ($app !== null) {
            $boot = $app->getBootstrap();
            if ($boot !== null && method_exists($boot, 'authorizeWebConsole')) {
                return (bool) $boot->authorizeWebConsole($request);
            }
        }

        if (self::isAllowedDuringMaintenance()
            && class_exists(\ZF1\System\MaintenanceMode::class)
            && \ZF1\System\MaintenanceMode::isDown()
        ) {
            return true;
        }

        if (function_exists('is_production')) {
            return ! is_production();
        }

        return defined('APPLICATION_ENV') && APPLICATION_ENV !== 'production';
    }

    /**
     * Dispatch the Web Console request (Terminal UI or Command Execution).
     */
    public static function handle(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response): void
    {
        // Disable Zend Layout so the standalone console view renders cleanly
        if (class_exists('Zend_Layout', false)) {
            $layout = Zend_Layout::getMvcInstance();
            if ($layout instanceof Zend_Layout) {
                $layout->disableLayout();
            }
        }

        $uri = $request->getRequestUri();

        if (self::isExecuteRequest($uri)) {
            self::handleExecute($request, $response);

            return;
        }

        self::renderView();
        exit(0);
    }

    /**
     * Handle command execution (SSE streaming or JSON buffered).
     */
    public static function handleExecute(Zend_Controller_Request_Http $request, Zend_Controller_Response_Http $response): void
    {
        if (! $request->isPost()) {
            self::sendJsonResponse(['success' => false, 'error' => 'Invalid request method. POST required.'], 405);

            return;
        }

        $commandStr = trim((string) $request->getPost('command', ''));
        if ($commandStr === '') {
            self::sendJsonResponse(['success' => false, 'error' => 'Please enter a command to execute.'], 400);

            return;
        }

        // Strip prefix: ./zf, php zf, zf
        $clean = (string) preg_replace('/^(?:\.\/zf|php\s+zf|zf)\s+/i', '', $commandStr);
        $clean = trim($clean);

        if ($clean === '') {
            self::sendJsonResponse(['success' => false, 'error' => 'Command string cannot be empty.'], 400);

            return;
        }

        $args = self::parseCommandArguments($clean);
        if (empty($args)) {
            self::sendJsonResponse(['success' => false, 'error' => 'Failed to parse command arguments.'], 400);

            return;
        }

        $commandName = $args[0];

        // Guard against restricted commands
        if (in_array($commandName, self::$hiddenCommands, true)) {
            self::sendJsonResponse(['success' => false, 'error' => "Command [{$commandName}] is restricted from web execution."], 403);

            return;
        }

        $startTime = microtime(true);
        $isStream = (bool) $request->getPost('stream', '1');

        if ($isStream) {
            self::streamCommandExecution($args, $clean, $startTime);

            return;
        }

        self::executeBuffered($args, $clean, $startTime);
    }

    /**
     * Stream command output live via Server-Sent Events (SSE).
     *
     * @param  array<string>  $args
     */
    protected static function streamCommandExecution(array $args, string $cleanCommand, float $startTime): void
    {
        // Close active session lock so concurrent HTTP requests aren't held up
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        ob_implicit_flush(true);

        echo 'data: '.json_encode(['type' => 'start', 'command' => $cleanCommand], JSON_UNESCAPED_SLASHES)."\n\n";
        @flush();

        ob_start(function (string $buffer): string {
            if ($buffer === '') {
                return '';
            }

            $payload = json_encode([
                'type' => 'output',
                'chunk' => $buffer,
                'html' => self::ansiToHtml($buffer),
            ], JSON_UNESCAPED_SLASHES);

            return "data: {$payload}\n\n";
        }, 1);

        try {
            $exitCode = Application::run($args);
        } catch (Throwable $e) {
            echo PHP_EOL."\033[31mException: ".$e->getMessage()."\033[0m".PHP_EOL;
            echo $e->getTraceAsString().PHP_EOL;
            $exitCode = 1;
        }

        @ob_end_flush();

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        echo 'data: '.json_encode([
            'type' => 'end',
            'exit_code' => $exitCode,
            'execution_time_ms' => $executionTime,
        ], JSON_UNESCAPED_SLASHES)."\n\n";

        @flush();
        exit(0);
    }

    /**
     * Execute command and return structured JSON response.
     *
     * @param  array<string>  $args
     */
    protected static function executeBuffered(array $args, string $cleanCommand, float $startTime): void
    {
        ob_start();
        try {
            $exitCode = Application::run($args);
        } catch (Throwable $e) {
            echo PHP_EOL."\033[31mException: ".$e->getMessage()."\033[0m".PHP_EOL;
            echo $e->getTraceAsString().PHP_EOL;
            $exitCode = 1;
        }
        $rawOutput = (string) ob_get_clean();

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        self::sendJsonResponse([
            'success' => true,
            'exit_code' => $exitCode,
            'command' => $cleanCommand,
            'full_command' => "php zf {$cleanCommand}",
            'raw_output' => $rawOutput,
            'html_output' => self::ansiToHtml($rawOutput),
            'execution_time_ms' => $executionTime,
        ]);
    }

    /**
     * Send JSON response and exit cleanly.
     *
     * @param  array<string, mixed>  $data
     */
    public static function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit(0);
    }

    /**
     * Render the Web Console HTML UI.
     */
    public static function renderView(): void
    {
        $viewPath = self::resolveCustomView();
        $commandsData = self::getCommandsData();

        $vars = [
            'appName' => self::title(),
            'homeUrl' => self::homeUrl(),
            'consolePath' => self::path(),
            'executeUrl' => rtrim(self::path(), '/').'/execute',
            'totalCommands' => $commandsData['total'],
            'groups' => $commandsData['groups'],
            'defaultTheme' => self::$theme,
            'customCss' => self::$customCss !== '' ? '<style>'.self::$customCss.'</style>' : '',
            'envName' => defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production',
            'isDown' => class_exists(\ZF1\System\MaintenanceMode::class) && \ZF1\System\MaintenanceMode::isDown(),
        ];

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');

        Kernel::ensureIncludePaths();

        if (class_exists('Zend_View')) {
            try {
                $view = new Zend_View;
                foreach ($vars as $k => $v) {
                    $view->{$k} = $v;
                }
                echo $view->render($viewPath);

                return;
            } catch (Throwable) {
                // Fallback to anonymous object context
            }
        }

        // Direct object scope execution where $this is guaranteed to exist
        $renderer = new #[AllowDynamicProperties] class($vars)
        {
            public function __construct(array $vars)
            {
                foreach ($vars as $k => $v) {
                    $this->{$k} = $v;
                }
            }

            public function render(string $templateFile): void
            {
                extract(get_object_vars($this), EXTR_SKIP);
                require $templateFile;
            }
        };

        $renderer->render($viewPath);
    }

    /**
     * Get and categorize all available commands.
     *
     * @return array{total: int, groups: array<string, array<int, array<string, mixed>>>}
     */
    public static function getCommandsData(): array
    {
        Application::boot();

        $uniqueCommands = Application::getUniqueCommands();
        $filtered = [];
        $appPath = function_exists('app_path') ? app_path() : Kernel::getApplicationPath();

        foreach ($uniqueCommands as $name => $cmd) {
            // Check hidden commands
            if (in_array($name, self::$hiddenCommands, true)) {
                continue;
            }

            // Check if only project commands requested
            if (self::$onlyProjectCommands) {
                $ref = new ReflectionClass($cmd);
                $file = $ref->getFileName();
                if (! $file || ! str_starts_with($file, $appPath) || str_contains($file, '/vendor/')) {
                    continue;
                }
            }

            $filtered[$name] = $cmd;
        }

        $grouped = [];
        foreach ($filtered as $name => $cmd) {
            $parts = explode(':', $name, 2);
            $group = count($parts) === 2 ? $parts[0] : 'general';

            $grouped[$group][] = [
                'name' => $name,
                'synopsis' => $cmd->getSynopsis(),
                'description' => $cmd->getDescription(),
                'aliases' => $cmd->getAliases(),
            ];
        }

        // Sort groups with priority groups first
        $priority = self::$priorityGroups;
        uksort($grouped, static function ($a, $b) use ($priority) {
            $posA = array_search(strtolower($a), $priority, true);
            $posB = array_search(strtolower($b), $priority, true);
            $posA = $posA !== false ? $posA : 999;
            $posB = $posB !== false ? $posB : 999;

            return $posA !== $posB ? $posA <=> $posB : strcmp($a, $b);
        });

        return [
            'total' => count($filtered),
            'groups' => $grouped,
        ];
    }

    /**
     * Parse shell command string into array of argument tokens.
     *
     * @return array<string>
     */
    public static function parseCommandArguments(string $commandStr): array
    {
        $args = [];
        if (preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', $commandStr, $matches)) {
            foreach ($matches[0] as $token) {
                $token = trim($token);
                if (
                    (str_starts_with($token, '"') && str_ends_with($token, '"')) ||
                    (str_starts_with($token, "'") && str_ends_with($token, "'"))
                ) {
                    $token = substr($token, 1, -1);
                }
                if ($token !== '') {
                    $args[] = $token;
                }
            }
        }

        return $args;
    }

    /**
     * Convert ANSI console color escape sequences into safe, styled HTML spans.
     */
    public static function ansiToHtml(string $text): string
    {
        // First escape HTML entities to prevent XSS
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $replacements = [
            '/\033\[0m/' => '</span>',
            '/\033\[31m/' => '<span style="color:#ef4444; font-weight:600;">',
            '/\033\[32m/' => '<span style="color:#22c55e; font-weight:600;">',
            '/\033\[33m/' => '<span style="color:#f59e0b; font-weight:600;">',
            '/\033\[34m/' => '<span style="color:#3b82f6; font-weight:600;">',
            '/\033\[35m/' => '<span style="color:#ec4899; font-weight:600;">',
            '/\033\[36m/' => '<span style="color:#06b6d4; font-weight:600;">',
            '/\033\[1m/' => '<span style="font-weight:bold;">',
            '/\033\[1;33m/' => '<span style="color:#fbbf24; font-weight:bold;">',
            '/\033\[[0-9;]*m/' => '',
        ];

        return (string) preg_replace(array_keys($replacements), array_values($replacements), $escaped);
    }

    /**
     * Resolve the active view template path.
     */
    public static function resolveCustomView(): string
    {
        if (self::$customView !== null && is_file(self::$customView)) {
            return self::$customView;
        }

        $appViewsDir = function_exists('app_path') ? app_path('views/scripts') : Kernel::getApplicationPath().'/views/scripts';
        $published = [
            $appViewsDir.'/console/index.phtml',
            $appViewsDir.'/zf-console.phtml',
            $appViewsDir.'/console.phtml',
        ];

        foreach ($published as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $stub = __DIR__.'/stubs/console.phtml.stub';

        return is_file($stub) ? $stub : $stub;
    }

    /**
     * Publish the customizable web terminal template into the consuming application.
     *
     * @param  string|null  $targetPath  Optional explicit destination path
     * @param  bool  $force  Overwrite if already exists
     * @return string Destination file path
     */
    public static function publishView(?string $targetPath = null, bool $force = false): string
    {
        $target = $targetPath ?: (function_exists('app_path') ? app_path('views/scripts/console/index.phtml') : Kernel::getApplicationPath().'/views/scripts/console/index.phtml');

        if (file_exists($target) && ! $force) {
            return $target;
        }

        $dir = dirname($target);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $stubFile = __DIR__.'/stubs/console.phtml.stub';
        if (! file_exists($stubFile)) {
            throw new RuntimeException("Framework web console stub template not found at [{$stubFile}].");
        }

        copy($stubFile, $target);

        return $target;
    }

    /**
     * Check if an IP address falls inside a CIDR subnet.
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskInt = (int) $mask;
            $maskLong = -1 << (32 - $maskInt);
            $subnetLong &= $maskLong;

            return ($ipLong & $maskLong) === $subnetLong;
        }

        return false;
    }

    /**
     * Dynamically forward instance method calls to static methods for fluent chaining.
     *
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists(self::class, $method)) {
            return self::{$method}(...$arguments);
        }

        return $this;
    }
}
