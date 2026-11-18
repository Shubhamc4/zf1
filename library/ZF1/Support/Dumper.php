<?php

declare(strict_types=1);

namespace ZF1\Support;

use Closure;
use Countable;
use Exception;
use JsonSerializable;
use ReflectionClass;
use Throwable;
use Zend_Db_Select;

/**
 * Modern Debugging and Inspection Engine for Zend Framework 1.
 */
final class Dumper
{
    /**
     * Dump the given variables and continue script execution.
     */
    public static function dump(mixed ...$vars): mixed
    {
        if (empty($vars)) {
            return null;
        }

        $caller = self::resolveCaller();
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        foreach ($vars as $var) {
            if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {
                \Symfony\Component\VarDumper\VarDumper::dump($var);

                continue;
            }

            if ($isCli) {
                self::renderCli($var, $caller);
            } elseif (function_exists('request_wants_json') && request_wants_json()) {
                self::renderJson($var, $caller);
            } else {
                self::renderHtml($var, $caller, false);
            }
        }

        return count($vars) === 1 ? $vars[0] : $vars;
    }

    /**
     * Dump the given variables and terminate execution ("dump and die").
     */
    public static function dd(mixed ...$vars): never
    {
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        if (! $isCli && ! headers_sent()) {
            if (http_response_code() === 200) {
                http_response_code(500);
            }
        }

        $caller = self::resolveCaller();

        foreach ($vars as $var) {
            if ($isCli) {
                self::renderCli($var, $caller);
            } elseif (function_exists('request_wants_json') && request_wants_json()) {
                self::renderJson($var, $caller);
            } else {
                self::renderHtml($var, $caller, true);
            }
        }

        exit(1);
    }

    /**
     * Dump the given variables only if the condition is truthy.
     */
    public static function dumpIf(mixed $condition, mixed ...$vars): mixed
    {
        if ($condition instanceof Closure) {
            $condition = $condition();
        }

        if ($condition) {
            return self::dump(...$vars);
        }

        return count($vars) === 1 ? ($vars[0] ?? null) : $vars;
    }

    /**
     * Dump the given variables and terminate execution only if the condition is truthy.
     */
    public static function ddIf(mixed $condition, mixed ...$vars): void
    {
        if ($condition instanceof Closure) {
            $condition = $condition();
        }

        if ($condition) {
            self::dd(...$vars);
        }
    }

    /**
     * Log the dumped representation of variables to the application log without printing.
     */
    public static function log(mixed ...$vars): void
    {
        $caller = self::resolveCaller();
        $timestamp = date('Y-m-d H:i:s');
        $lines = [];
        $lines[] = "[{$timestamp}] [ZF1 DLOG] {$caller['file']}:{$caller['line']}";

        foreach ($vars as $var) {
            $lines[] = self::formatVariable($var);
        }

        $logEntry = implode(PHP_EOL, $lines).PHP_EOL;

        $logPath = null;
        if (function_exists('storage_path')) {
            $dir = storage_path('logs');
            if (is_dir($dir) || @mkdir($dir, 0775, true)) {
                $logPath = $dir.DIRECTORY_SEPARATOR.'zf1.log';
            }
        }

        if ($logPath !== null) {
            @file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
        } else {
            error_log($logEntry);
        }
    }

    /**
     * Dump or print a clean stack trace.
     */
    public static function trace(?Throwable $e = null, bool $exit = false): string
    {
        $caller = self::resolveCaller();
        $trace = $e ? $e->getTraceAsString() : (new Exception)->getTraceAsString();

        if (function_exists('formatCleanTrace')) {
            $trace = formatCleanTrace($trace, true);
        }

        $callerStr = "Stack trace triggered at: {$caller['file']}:{$caller['line']}";
        $output = $callerStr.PHP_EOL.$trace;

        self::dump($output);

        if ($exit) {
            exit(1);
        }

        return $output;
    }

    /**
     * Measure the execution time and memory consumption of a callback.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function benchmark(callable $callback, int $iterations = 1, ?string $label = null): mixed
    {
        $iterations = max(1, $iterations);
        $startMem = memory_get_usage();
        $startTime = hrtime(true);

        $result = null;
        for ($i = 0; $i < $iterations; $i++) {
            $result = $callback();
        }

        $endTime = hrtime(true);
        $endMem = memory_get_usage();
        $peakMem = memory_get_peak_usage();

        $durationMs = ($endTime - $startTime) / 1_000_000;
        $avgMs = $durationMs / $iterations;
        $memDiff = $endMem - $startMem;

        $formatBytes = function (int|float $bytes): string {
            if (function_exists('formatBytes')) {
                return formatBytes($bytes);
            }

            return number_format($bytes / 1024, 2).' KB';
        };

        $stats = [
            'label' => $label ?? 'Benchmark',
            'iterations' => $iterations,
            'total_time' => sprintf('%.4f ms', $durationMs),
            'avg_time' => sprintf('%.4f ms', $avgMs),
            'memory_used' => $formatBytes(max(0, $memDiff)),
            'peak_memory' => $formatBytes($peakMem),
        ];

        self::dump($stats);

        return $result;
    }

    /**
     * Interpolate and format an SQL query from a Zend_Db_Select or SQL string.
     */
    public static function dumpSql(mixed $query, array $bind = []): string
    {
        $sql = class_exists(\ZF1\Database\DB::class)
            ? \ZF1\Database\DB::toRawSql($query, $bind)
            : self::interpolateSql($query, $bind);

        $caller = self::resolveCaller();
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        if ($isCli) {
            self::renderCli($sql, $caller);
        } elseif (function_exists('request_wants_json') && request_wants_json()) {
            self::renderJson($sql, $caller);
        } else {
            self::renderHtml($sql, $caller, false);
        }

        return $sql;
    }

    /**
     * Interpolate an SQL query, dump it, and terminate execution.
     */
    public static function ddSql(mixed $query, array $bind = []): never
    {
        $sql = class_exists(\ZF1\Database\DB::class)
            ? \ZF1\Database\DB::toRawSql($query, $bind)
            : self::interpolateSql($query, $bind);

        $caller = self::resolveCaller();
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        if (! $isCli && ! headers_sent() && http_response_code() === 200) {
            http_response_code(500);
        }

        if ($isCli) {
            self::renderCli($sql, $caller);
        } elseif (function_exists('request_wants_json') && request_wants_json()) {
            self::renderJson($sql, $caller);
        } else {
            self::renderHtml($sql, $caller, true);
        }

        exit(1);
    }

    /**
     * Interpolate basic SQL bindings when DB manager is not loaded.
     */
    protected static function interpolateSql(mixed $query, array $bind = []): string
    {
        $sql = '';

        if ($query instanceof Zend_Db_Select) {
            $sql = (string) $query->assemble();
            if (empty($bind)) {
                $bind = $query->getBind();
            }
        } elseif (is_string($query)) {
            $sql = $query;
        } elseif (is_object($query) && method_exists($query, '__toString')) {
            $sql = (string) $query;
        }

        if (! empty($bind)) {
            foreach ($bind as $key => $val) {
                $replacement = is_numeric($val) ? (string) $val : "'".addslashes((string) $val)."'";
                if (is_string($key)) {
                    $keyPattern = ':'.ltrim($key, ':');
                    $sql = str_replace($keyPattern, $replacement, $sql);
                } else {
                    $sql = preg_replace('/\?/', $replacement, $sql, 1);
                }
            }
        }

        return $sql;
    }

    /**
     * Format a variable as string via var_dump buffer.
     */
    public static function formatVariable(mixed $var): string
    {
        ob_start();
        var_dump($var);
        $output = (string) ob_get_clean();

        return preg_replace("/\]\=\>\n(\s+)/m", '] => ', $output);
    }

    /**
     * Render CLI formatted output.
     */
    public static function renderCli(mixed $var, array $caller): void
    {
        $output = self::formatVariable($var);
        $hasColor = self::hasColorSupport();

        if ($hasColor) {
            echo "\033[1;36m// [ZF1 Dump]\033[0m \033[90m{$caller['file']}:{$caller['line']}\033[0m".PHP_EOL;
        } else {
            echo "// [ZF1 Dump] {$caller['file']}:{$caller['line']}".PHP_EOL;
        }

        echo rtrim($output).PHP_EOL.PHP_EOL;
    }

    /**
     * Render elegant full-screen HTML card output for browsers with one-click copy.
     */
    public static function renderHtml(mixed $var, array $caller, bool $isTerminating = false): void
    {
        static $styleRendered = false;
        $appName = function_exists('app_name') ? app_name() : (defined('APP_NAME') ? (string) APP_NAME : 'ZF1');
        $callerDisplay = htmlspecialchars("{$caller['file']}:{$caller['line']}", ENT_QUOTES, 'UTF-8');
        $containerId = 'zf1_dump_box_'.bin2hex(random_bytes(6));
        $copyId = 'zf1_dump_copy_'.bin2hex(random_bytes(6));

        $isSql = is_string($var) && (bool) preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $var);

        $hasJson = false;
        $displayRaw = htmlspecialchars(self::formatVariable($var), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $displayJson = '';
        $rawCopyPayload = self::formatPhpArray($var);

        if ($isSql) {
            $typeLabel = 'SQL Query';
            $badgeColor = '#8250df';
            $copyLabel = 'Copy SQL';
            $copyPayload = trim($var);
            $displayContent = self::highlightSql(trim($var));
        } elseif ($var instanceof \ZF1\Database\FluentRow) {
            $typeLabel = 'FluentRow ('.count($var).')';
            $badgeColor = '#0969da';
            $copyLabel = 'Copy JSON';
            $json = $var->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $copyPayload = $json;
            $hasJson = true;
            $displayJson = self::highlightJson($json);
            $displayContent = $displayJson;
        } elseif ($var instanceof \ZF1\Database\FluentCollection) {
            $typeLabel = 'FluentCollection ('.count($var).')';
            $badgeColor = '#0969da';
            $copyLabel = 'Copy JSON';
            $json = $var->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $copyPayload = $json;
            $hasJson = true;
            $displayJson = self::highlightJson($json);
            $displayContent = $displayJson;
        } elseif ($var instanceof \ZF1\Database\Paginator) {
            $typeLabel = 'Paginator ('.$var->total().')';
            $badgeColor = '#0969da';
            $copyLabel = 'Copy JSON';
            $json = $var->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $copyPayload = $json;
            $hasJson = true;
            $displayJson = self::highlightJson($json);
            $displayContent = $displayJson;
        } elseif (is_array($var)) {
            $typeLabel = 'Array ('.count($var).')';
            $badgeColor = '#1f6feb';
            $copyLabel = 'Copy JSON';
            $json = json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $hasJson = true;
                $displayJson = self::highlightJson($json);
                $displayContent = $displayJson;
                $copyPayload = $json;
            } else {
                $copyPayload = print_r($var, true);
                $displayContent = $displayRaw;
            }
        } elseif (is_object($var)) {
            $shortName = (new ReflectionClass($var))->getShortName();
            $countSuffix = $var instanceof Countable ? ' ('.count($var).')' : '';
            $typeLabel = $shortName.$countSuffix;
            $badgeColor = '#0969da';
            $copyLabel = 'Copy JSON';
            $json = json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false && $json !== '{}' && $json !== '[]') {
                $hasJson = true;
                $displayJson = self::highlightJson($json);
                $displayContent = $displayJson;
                $copyPayload = $json;
            } else {
                $copyPayload = print_r($var, true);
                $displayContent = $displayRaw;
            }
        } else {
            $typeLabel = gettype($var).(is_string($var) ? ' ('.strlen($var).')' : '');
            $badgeColor = '#238636';
            $copyLabel = 'Copy';
            $copyPayload = is_string($var) ? $var : self::formatVariable($var);
            $displayContent = $displayRaw;
        }

        $includeStyles = ! $styleRendered;
        $styleRendered = true;

        $stub = __DIR__.'/stubs/dump.phtml.stub';

        if (is_file($stub)) {
            (static function () use ($stub, $appName, $callerDisplay, $containerId, $copyId, $typeLabel, $badgeColor, $copyLabel, $copyPayload, $displayContent, $hasJson, $displayJson, $displayRaw, $rawCopyPayload, $includeStyles, $isTerminating): void {
                include $stub;
            })();
        }
    }

    /**
     * Syntax highlight a JSON string with modern IDE colors.
     */
    public static function highlightJson(string $json): string
    {
        $escaped = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace_callback(
            '/("(\\\\u[a-zA-Z0-9]{4}|\\\\[^u]|[^\\\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/',
            function (array $matches): string {
                $match = $matches[0];
                if (str_starts_with($match, '"')) {
                    if (str_ends_with(rtrim($match), ':')) {
                        return '<span style="color: #79c0ff; font-weight: 600;">'.$match.'</span>';
                    }

                    return '<span style="color: #7ee787;">'.$match.'</span>';
                }

                if ($match === 'true' || $match === 'false') {
                    return '<span style="color: #ff7b72; font-weight: 600;">'.$match.'</span>';
                }

                if ($match === 'null') {
                    return '<span style="color: #8b949e; font-style: italic;">'.$match.'</span>';
                }

                return '<span style="color: #ffa657;">'.$match.'</span>';
            },
            $escaped
        );
    }

    /**
     * Export variable as clean, short-array syntax PHP code.
     */
    public static function formatPhpArray(mixed $var): string
    {
        $array = null;
        if ($var instanceof \ZF1\Database\FluentRow) {
            $array = $var->toArray();
        } elseif ($var instanceof \ZF1\Database\FluentCollection) {
            $array = $var->toArray();
        } elseif ($var instanceof \ZF1\Database\Paginator) {
            $array = $var->toArray();
        } elseif (is_array($var)) {
            $array = $var;
        } elseif ($var instanceof JsonSerializable) {
            $array = $var->jsonSerialize();
        } elseif (is_object($var)) {
            $array = (array) $var;
        }

        if (is_array($array)) {
            $export = var_export($array, true);
            $patterns = [
                "/array \(\n/" => "[\n",
                "/array\(\n/" => "[\n",
                "/^([ ]*)\)(,?)$/m" => '$1]$2',
                "/=>[ ]?\n[ ]+array \(/s" => '=> [',
                "/=>[ ]?\n[ ]+array\(/s" => '=> [',
            ];
            $short = preg_replace(array_keys($patterns), array_values($patterns), $export);

            return $short !== null ? $short : $export;
        }

        return self::formatVariable($var);
    }

    /**
     * Syntax highlight an SQL query string for browser display.
     */
    protected static function highlightSql(string $sql): string
    {
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'ORDER BY', 'GROUP BY', 'LIMIT', 'OFFSET',
            'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'CROSS JOIN', 'JOIN', 'ON', 'HAVING',
            'INSERT INTO', 'INSERT', 'UPDATE', 'DELETE FROM', 'DELETE', 'SET', 'VALUES',
            'DESC', 'ASC', 'IN', 'IS NULL', 'IS NOT NULL', 'IS', 'NOT', 'NULL', 'LIKE', 'BETWEEN',
            'UNION ALL', 'UNION', 'AS', 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'DISTINCT',
        ];
        usort($keywords, fn ($a, $b) => strlen($b) <=> strlen($a));

        $escaped = htmlspecialchars($sql, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Highlight strings in quotes
        $escaped = preg_replace("/(&#039;|')[^'&#]*(?:&#039;|')/", '<span style="color:#a5d6ff;">$0</span>', $escaped);

        // Highlight backticks
        $escaped = preg_replace('/`[^`]+`/', '<span style="color:#7ee787;">$0</span>', $escaped);

        // Highlight keywords
        foreach ($keywords as $kw) {
            $escaped = preg_replace('/\b'.preg_quote($kw, '/').'\b/i', '<span style="color:#ff7b72;font-weight:600;">$0</span>', $escaped);
        }

        return $escaped;
    }

    /**
     * Render JSON output for API/JSON requests.
     */
    public static function renderJson(mixed $var, array $caller): void
    {
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            '__zf1_dump' => true,
            'caller' => "{$caller['file']}:{$caller['line']}",
            'payload' => $var,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL;
    }

    /**
     * Resolve the source file and line number of the caller.
     *
     * @return array{file: string, full_file: string, line: int}
     */
    public static function resolveCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $fallback = null;

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file === '' || str_contains($file, 'Dumper.php') || str_contains($file, 'helpers.php') || str_contains($file, 'FluentRow.php') || str_contains($file, 'FluentCollection.php') || str_contains($file, 'Paginator.php')) {
                continue;
            }

            if ($fallback === null) {
                $fallback = $frame;
            }

            if (self::isFrameworkOrVendor($file)) {
                continue;
            }

            return self::formatCallerFrame($frame);
        }

        if ($fallback !== null) {
            return self::formatCallerFrame($fallback);
        }

        return [
            'file' => 'unknown',
            'full_file' => 'unknown',
            'line' => 0,
        ];
    }

    /**
     * Determine if a file path belongs to the framework or vendor packages.
     */
    protected static function isFrameworkOrVendor(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);

        if (str_contains($normalized, 'Dumper.php')
            || str_contains($normalized, 'helpers.php')
            || str_contains($normalized, 'ValidatedInput.php')
            || str_contains($normalized, 'FluentRow.php')
            || str_contains($normalized, 'FluentCollection.php')
            || str_contains($normalized, 'Paginator.php')
            || str_contains($normalized, 'Zend/Db/Select.php')
            || str_contains($normalized, 'ZF1/Database/DB.php')) {
            return true;
        }

        if (str_contains($normalized, '/vendor/')) {
            return true;
        }

        if (str_contains($normalized, '/library/Zend/') || str_contains($normalized, '/library/ZF1/')) {
            return true;
        }

        return false;
    }

    /**
     * Format a backtrace frame into caller information with root-relative path.
     *
     * @param  array{file?: string, line?: int}  $frame
     * @return array{file: string, full_file: string, line: int}
     */
    protected static function formatCallerFrame(array $frame): array
    {
        $file = $frame['file'] ?? 'unknown';
        $line = $frame['line'] ?? 0;
        $displayFile = $file;

        if (class_exists(\ZF1\Foundation\Kernel::class)) {
            $root = \ZF1\Foundation\Kernel::getProjectRoot();
            if ($root !== '' && str_starts_with($file, $root)) {
                $displayFile = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);
            }
        } elseif (function_exists('base_path')) {
            $root = base_path();
            if ($root !== '' && str_starts_with($file, $root)) {
                $displayFile = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);
            }
        }

        return [
            'file' => $displayFile,
            'full_file' => $file,
            'line' => $line,
        ];
    }

    /**
     * Determine if current terminal supports ANSI color codes.
     */
    protected static function hasColorSupport(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        }

        return function_exists('posix_isatty') ? @posix_isatty(STDOUT) : true;
    }
}
