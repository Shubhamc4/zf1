<?php

declare(strict_types=1);

/**
 * ZF1 Universal Framework Helpers
 * Available across all ZF1 projects.
 */
if (! function_exists('env')) {
    /**
     * Get an environment variable value with optional default fallback.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return ZF1\Environment\Env::get($key, $default);
    }
}

if (! function_exists('app_env')) {
    /**
     * Get the active application environment name (e.g. 'production', 'local', 'testing').
     */
    function app_env(): string
    {
        return defined('APPLICATION_ENV') ? (string) APPLICATION_ENV : (string) env('APP_ENV', 'production');
    }
}

if (! function_exists('is_production')) {
    /**
     * Check if the application is currently running in a production environment.
     */
    function is_production(): bool
    {
        $env = strtolower(app_env());

        return in_array($env, ['production', 'prod', 'live'], true);
    }
}

if (! function_exists('is_local')) {
    /**
     * Check if the application is currently running in a local environment.
     */
    function is_local(): bool
    {
        $env = strtolower(app_env());

        return in_array($env, ['local', 'dev', 'development'], true);
    }
}

if (! function_exists('is_development')) {
    /**
     * Alias for is_local().
     */
    function is_development(): bool
    {
        return is_local();
    }
}

if (! function_exists('is_testing')) {
    /**
     * Check if the application is running in a testing environment.
     */
    function is_testing(): bool
    {
        return strtolower(app_env()) === 'testing';
    }
}

if (! function_exists('is_staging')) {
    /**
     * Check if the application is running in a staging or UAT environment.
     */
    function is_staging(): bool
    {
        $env = strtolower(app_env());

        return in_array($env, ['staging', 'stage', 'uat'], true);
    }
}

if (! function_exists('app_name')) {
    /**
     * Get the configured application name.
     */
    function app_name(): string
    {
        return defined('APP_NAME') ? (string) APP_NAME : (string) env('APP_NAME', 'ZF1');
    }
}

if (! function_exists('app_url')) {
    /**
     * Get the configured application base URL or an absolute URL for a path.
     */
    function app_url(string $path = ''): string
    {
        $base = rtrim((string) env('APP_URL', 'http://localhost'), '/');

        return $path !== '' ? "{$base}/".ltrim($path, '/') : $base;
    }
}

if (! function_exists('is_cli')) {
    /**
     * Check if current execution context is Command Line Interface.
     */
    function is_cli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (! function_exists('base_path')) {
    /**
     * Get the project root directory or a subpath within it.
     */
    function base_path(string $path = ''): string
    {
        $base = ZF1\Foundation\Kernel::getProjectRoot();

        return $path !== '' ? $base.DIRECTORY_SEPARATOR.ltrim($path, '/\\') : $base;
    }
}

if (! function_exists('app_path')) {
    /**
     * Get the application directory or a subpath within it.
     */
    function app_path(string $path = ''): string
    {
        $app = ZF1\Foundation\Kernel::getApplicationPath();

        return $path !== '' ? $app.DIRECTORY_SEPARATOR.ltrim($path, '/\\') : $app;
    }
}

if (! function_exists('storage_path')) {
    /**
     * Get the storage directory or a subpath within it.
     */
    function storage_path(string $path = ''): string
    {
        $storage = ZF1\Foundation\Kernel::getStoragePath();

        return $path !== '' ? $storage.DIRECTORY_SEPARATOR.ltrim($path, '/\\') : $storage;
    }
}

if (! function_exists('public_path')) {
    /**
     * Get the public web directory or a subpath within it.
     */
    function public_path(string $path = ''): string
    {
        $public = ZF1\Foundation\Kernel::getPublicPath();

        return $path !== '' ? $public.DIRECTORY_SEPARATOR.ltrim($path, '/\\') : $public;
    }
}

if (! function_exists('config_path')) {
    /**
     * Get the application configs directory or a subpath within it.
     */
    function config_path(string $path = ''): string
    {
        $config = app_path('configs');

        return $path !== '' ? $config.DIRECTORY_SEPARATOR.ltrim($path, '/\\') : $config;
    }
}

if (! function_exists('url')) {
    /**
     * Generate an absolute URL for the application.
     *
     * @param  string  $path  Path relative to application base URL
     * @param  array<string, mixed>  $parameters  Optional query parameters
     * @param  bool|null  $secure  Force HTTPS if true, HTTP if false, default if null
     * @param  string|null  $root  Explicit base URL root override (e.g. ASSET_URL)
     */
    function url(string $path = '', array $parameters = [], ?bool $secure = null, ?string $root = null): string
    {
        $baseUrl = $root ?? (string) env('APP_URL', '');

        if ($baseUrl === '' && isset($_SERVER['HTTP_HOST'])) {
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
            $scheme = $isHttps ? 'https' : 'http';
            $baseUrl = "{$scheme}://{$_SERVER['HTTP_HOST']}";
        }

        $baseUrl = rtrim($baseUrl, '/');

        if ($secure !== null && $baseUrl !== '') {
            $baseUrl = $secure
                ? preg_replace('/^http:\/\//i', 'https://', $baseUrl)
                : preg_replace('/^https:\/\//i', 'http://', $baseUrl);
        }

        $cleanPath = ltrim($path, '/\\');
        $url = $cleanPath !== '' ? "{$baseUrl}/{$cleanPath}" : $baseUrl;

        if (! empty($parameters)) {
            $queryString = http_build_query($parameters);
            $url .= (str_contains($url, '?') ? '&' : '?').$queryString;
        }

        return $url;
    }
}

if (! function_exists('asset')) {
    /**
     * Generate an asset URL for the application.
     *
     * @param  string  $path  Asset path relative to the public directory
     * @param  bool|string|null  $version  Cache-busting version: true (filemtime), string (explicit), or null/false
     * @param  bool|null  $secure  Force HTTPS if true, HTTP if false, default if null
     */
    function asset(string $path = '', bool|string|null $version = null, ?bool $secure = null): string
    {
        $assetRoot = (string) env('ASSET_URL', '');
        $url = url($path, [], $secure, $assetRoot !== '' ? $assetRoot : null);

        if ($version === true) {
            $fullPath = public_path(ltrim($path, '/\\'));
            $v = is_file($fullPath) ? (string) filemtime($fullPath) : (string) env('APP_VERSION', time());
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$v;
        } elseif (is_string($version) && $version !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$version;
        }

        return $url;
    }
}

if (! function_exists('storage')) {
    /**
     * Get a storage filesystem disk instance.
     */
    function storage(?string $disk = null): ZF1\Filesystem\FilesystemAdapter
    {
        return ZF1\Filesystem\Storage::disk($disk);
    }
}

if (! class_exists('Storage', false)) {
    class_alias(ZF1\Filesystem\Storage::class, 'Storage');
}

if (! class_exists('Mailer', false)) {
    class_alias(ZF1\Mail\Mailer::class, 'Mailer');
}

if (! class_exists('EmailLayout', false)) {
    class_alias(ZF1\Mail\EmailLayout::class, 'EmailLayout');
}

if (! class_exists('WebConsole', false)) {
    class_alias(ZF1\Console\WebConsole::class, 'WebConsole');
}

if (! class_exists('DB', false)) {
    class_alias(ZF1\Database\DB::class, 'DB');
}

if (! class_exists('Cache', false)) {
    class_alias(ZF1\Cache\Cache::class, 'Cache');
}

if (! class_exists('Session', false)) {
    class_alias(ZF1\Session\Session::class, 'Session');
}

if (! class_exists('Optional', false)) {
    class_alias(ZF1\Support\Optional::class, 'Optional');
}

if (! class_exists('Validator', false)) {
    class_alias(ZF1\Validation\Validator::class, 'Validator');
}

if (! function_exists('mailer')) {
    /**
     * Get a new Mailer instance or create and configure with recipient.
     *
     * @param  string|array<string, string>|array<string>|null  $to
     */
    function mailer(string|array|null $to = null, string $name = ''): ZF1\Mail\Mailer
    {
        $mailer = new ZF1\Mail\Mailer;

        if ($to !== null) {
            $mailer->addTo($to, $name);
        }

        return $mailer;
    }
}

if (! function_exists('render_email_layout')) {
    /**
     * Render HTML or text content inside the email layout.
     *
     * @param  string  $content  Content body to inject into layout card
     * @param  string|null  $subject  Email subject for title
     * @param  array<string, mixed>  $data  Extra variables to pass to the layout
     */
    function render_email_layout(string $content, ?string $subject = null, array $data = []): string
    {
        return ZF1\Mail\EmailLayout::render($content, $subject, $data);
    }
}

if (! function_exists('db')) {
    /**
     * Get the active Zend_Db_Adapter_Abstract instance.
     */
    function db(?string $connection = null): Zend_Db_Adapter_Abstract
    {
        return ZF1\Database\DB::connection($connection);
    }
}

if (! function_exists('db_transaction')) {
    /**
     * Execute a callback inside a database transaction.
     *
     * @template T
     *
     * @param  callable(Zend_Db_Adapter_Abstract): T  $callback
     * @return T
     */
    function db_transaction(callable $callback, int $attempts = 1, ?string $connection = null): mixed
    {
        return ZF1\Database\DB::transaction($callback, $attempts, $connection);
    }
}

if (! function_exists('cache')) {
    /**
     * Get / set cache values or retrieve the Cache facade.
     */
    function cache(mixed $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return new ZF1\Cache\Cache;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                ZF1\Cache\Cache::put((string) $k, $v);
            }

            return true;
        }

        return ZF1\Cache\Cache::get((string) $key, $default);
    }
}

if (! function_exists('session')) {
    /**
     * Get / set session values or retrieve the Session manager.
     *
     * @param  string|array<string, mixed>|null  $key
     * @return mixed|ZF1\Session\Session
     */
    function session(mixed $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return new ZF1\Session\Session;
        }

        if (is_array($key)) {
            ZF1\Session\Session::put($key);

            return true;
        }

        return ZF1\Session\Session::get((string) $key, $default);
    }
}

if (! function_exists('redirect')) {
    /**
     * Create a new RedirectResponse instance.
     */
    function redirect(?string $to = null, int $status = 302): ZF1\Foundation\RedirectResponse
    {
        return new ZF1\Foundation\RedirectResponse($to, $status);
    }
}

if (! function_exists('back')) {
    /**
     * Create a redirect response back to the previous URL.
     */
    function back(?string $fallback = '/', int $status = 302): ZF1\Foundation\RedirectResponse
    {
        return (new ZF1\Foundation\RedirectResponse(null, $status))->back($fallback, $status);
    }
}

if (! function_exists('old')) {
    /**
     * Retrieve a flashed old input value from the session.
     */
    function old(?string $key = null, mixed $default = null): mixed
    {
        $old = ZF1\Session\Session::get('_old_input', []);
        if (! is_array($old)) {
            $old = [];
        }

        if ($key === null) {
            return $old;
        }

        return function_exists('data_get') ? data_get($old, $key, $default) : ($old[$key] ?? $default);
    }
}

if (! function_exists('optional')) {
    /**
     * Provide access to optional objects and values.
     *
     * @template T
     *
     * @param  T  $value
     * @param  (callable(T): mixed)|null  $callback
     * @return mixed|ZF1\Support\Optional
     */
    function optional(mixed $value = null, ?callable $callback = null): mixed
    {
        if ($callback === null) {
            if ($value instanceof ZF1\Support\Optional) {
                return $value;
            }

            return new ZF1\Support\Optional($value);
        }

        if ($value !== null) {
            return $callback($value);
        }

        return null;
    }
}

if (! function_exists('validator')) {
    /**
     * Create a new Validator instance.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $customAttributes
     */
    function validator(
        array $data = [],
        array $rules = [],
        array $messages = [],
        array $customAttributes = []
    ): ZF1\Validation\Validator {
        return ZF1\Validation\Validator::make($data, $rules, $messages, $customAttributes);
    }
}

if (! function_exists('ensure_directory_exists')) {
    /**
     * Ensure the given directory (or parent directory of a file) exists.
     */
    function ensure_directory_exists(string $path, int $mode = 0775): string
    {
        $dir = is_dir($path) || ! pathinfo($path, PATHINFO_EXTENSION) ? $path : dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, $mode, true);
        }

        return $path;
    }
}

if (! function_exists('is_valid_ip')) {
    function is_valid_ip(string $ip, bool $publicOnly = false): bool
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if ($publicOnly) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
    }
}

if (! function_exists('getIpAddress')) {
    function getIpAddress(): string
    {
        return get_client_ip();
    }
}

if (! function_exists('get_client_ip')) {
    function get_client_ip(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $raw = (string) $_SERVER[$header];

            if (str_contains($raw, ',')) {
                $parts = explode(',', $raw);
                foreach ($parts as $part) {
                    $ip = trim($part);
                    if (is_valid_ip($ip, true)) {
                        return $ip;
                    }
                }
                foreach ($parts as $part) {
                    $ip = trim($part);
                    if (is_valid_ip($ip, false)) {
                        return $ip;
                    }
                }
            } else {
                $ip = trim($raw);
                if (is_valid_ip($ip, false)) {
                    return $ip;
                }
            }
        }

        return ! empty($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
}

if (! function_exists('random_strings')) {
    /**
     * Generate a random alphanumeric string.
     */
    function random_strings(int $length = 8): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        return substr(str_shuffle($chars), 0, max(1, $length));
    }
}

if (! function_exists('generate_username')) {
    /**
     * Generate username from full name or default string.
     */
    function generate_username(string $string = 'Guest'): string
    {
        $parts = array_filter(explode(' ', strtolower(trim($string))));
        $first = ! empty($parts) ? reset($parts) : 'Guest';
        $sanitized = preg_replace('/[\W]/', '', $first) ?: 'guest';

        return substr($sanitized, 0, 12).rand(1, 999);
    }
}

if (! function_exists('str_length')) {
    /**
     * Return multibyte string length.
     */
    function str_length(string $value, ?string $encoding = null): int
    {
        return mb_strlen($value, $encoding);
    }
}

if (! function_exists('str_words')) {
    /**
     * Limit the number of words in a string.
     */
    function str_words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,'.max(1, $words).'}/u', $value, $matches);

        if (! isset($matches[0]) || str_length($value) === str_length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]).$end;
    }
}

if (! function_exists('str_limit')) {
    /**
     * Limit string character length safely with multibyte support.
     */
    function str_limit(string $value, int $limit = 100, string $end = '...', bool $preserveWords = false): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        if (! $preserveWords) {
            return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')).$end;
        }

        $cleaned = trim((string) preg_replace('/[\n\r]+/', ' ', strip_tags($value)));
        $trimmed = rtrim(mb_strimwidth($cleaned, 0, $limit, '', 'UTF-8'));

        if (mb_substr($cleaned, $limit, 1, 'UTF-8') === ' ') {
            return $trimmed.$end;
        }

        return preg_replace('/(.*)\s.*/', '$1', $trimmed).$end;
    }
}

if (! function_exists('limitString')) {
    /**
     * Alias for str_limit with null handling.
     */
    function limitString(?string $value, int $limit = 25, string $end = '...'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return str_limit($value, $limit, $end);
    }
}

if (! function_exists('str_slug')) {
    /**
     * Generate a URL friendly "slug" from a given string.
     */
    function str_slug(string $title, string $separator = '-'): string
    {
        if (function_exists('transliterator_transliterate')) {
            $title = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0100-\u7fff] remove', $title);
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
            if ($converted !== false) {
                $title = $converted;
            }
        }

        $title = preg_replace('/[^a-zA-Z0-9]+/', $separator, $title) ?? '';
        $quotedSep = preg_quote($separator, '/');

        return trim((string) preg_replace("/{$quotedSep}+/", $separator, strtolower($title)), $separator);
    }
}

if (! function_exists('str_uuid')) {
    /**
     * Generate a cryptographically secure UUID (version 4).
     */
    function str_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (! function_exists('str_random')) {
    /**
     * Generate a cryptographically secure random alphanumeric string.
     */
    function str_random(int $length = 16): string
    {
        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }
}

if (! function_exists('str_mask')) {
    /**
     * Mask a portion of a string with a repeated character.
     */
    function str_mask(string $string, string $character = '*', int $index = 0, ?int $length = null): string
    {
        if ($character === '') {
            return $string;
        }

        $strLen = mb_strlen($string, 'UTF-8');
        if ($strLen === 0) {
            return '';
        }

        if ($index < 0) {
            $index = max(0, $strLen + $index);
        }

        if ($length === null) {
            $length = $strLen - $index;
        } elseif ($length < 0) {
            $length = max(0, $strLen - $index + $length);
        }

        $start = mb_substr($string, 0, $index, 'UTF-8');
        $mask = str_repeat($character, min($length, $strLen - $index));
        $end = mb_substr($string, $index + $length, null, 'UTF-8');

        return $start.$mask.$end;
    }
}

if (! function_exists('str_headline')) {
    /**
     * Convert a string into headline / Title Case (e.g. 'order_status_id' -> 'Order Status Id').
     */
    function str_headline(string $value): string
    {
        $parts = explode(' ', trim((string) preg_replace('/[_\-\s]+/', ' ', $value)));

        return ucwords(strtolower(implode(' ', $parts)));
    }
}

if (! function_exists('str_contains_any')) {
    /**
     * Determine if a given string contains any of the provided needles.
     *
     * @param  array<string>  $needles
     */
    function str_contains_any(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('str_starts_with_any')) {
    /**
     * Determine if a given string starts with any of the provided needles.
     *
     * @param  array<string>  $needles
     */
    function str_starts_with_any(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('str_ends_with_any')) {
    /**
     * Determine if a given string ends with any of the provided needles.
     *
     * @param  array<string>  $needles
     */
    function str_ends_with_any(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('pluck')) {
    /**
     * Pluck specific columns from a multidimensional array or array of associative items.
     *
     * @param  array<mixed>  $data
     * @param  array<string>  $columns
     * @return array<mixed>
     */
    function pluck(array $data, array $columns): array
    {
        if (empty($data)) {
            return [];
        }

        $keys = array_fill_keys(array_values($columns), '');
        $out = [];

        if (! empty($data[0]) && is_array($data[0])) {
            for ($i = 0, $len = count($data); $i < $len; $i++) {
                $out[$i] = array_filter($data[$i], static fn ($key) => in_array($key, $columns, true) || empty($columns), ARRAY_FILTER_USE_KEY);
                $out[$i] = array_merge($keys, $out[$i]);
            }
        } else {
            foreach ($data as $key => $value) {
                if (in_array($key, $columns, true) || empty($columns)) {
                    $out[$key] = $value;
                }
            }
        }

        return $out;
    }
}

if (! function_exists('flatten')) {
    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    function flatten(array $array): array
    {
        $return = [];
        array_walk_recursive($array, static function ($a) use (&$return) {
            $return[] = $a;
        });

        return $return;
    }
}

if (! function_exists('productCrossJoin')) {
    /**
     * Generate Cartesian cross join of an array with itself excluding self-pairs.
     *
     * @param  array<mixed>  $arr
     * @return array<array<mixed>>
     */
    function productCrossJoin(array $arr): array
    {
        if (empty($arr)) {
            return [];
        }

        $results = [];
        foreach ($arr as $baseId) {
            foreach ($arr as $joinId) {
                if ($baseId === $joinId) {
                    continue;
                }
                $results[] = [$baseId, $joinId];
            }
        }

        return $results;
    }
}

if (! function_exists('array_group_by')) {
    /**
     * Group array items by a key or callable.
     */
    function array_group_by(array $array, string|int|callable $key): ?array
    {
        if (empty($array)) {
            return [];
        }

        $grouped = [];
        foreach ($array as $value) {
            $groupKey = is_callable($key) ? $key($value) : ($value[$key] ?? null);
            if ($groupKey === null) {
                continue;
            }
            $grouped[$groupKey][] = $value;
        }

        return $grouped;
    }
}

if (! function_exists('parseCSV')) {
    /**
     * Parse a CSV file into an associative or indexed array.
     *
     * @return array If $has_header is true, returns [$data, $header], otherwise $data.
     */
    function parseCSV(string $filepath, bool $has_header = true): array
    {
        if (! file_exists($filepath) || ! is_readable($filepath)) {
            return $has_header ? [[], []] : [];
        }

        $data = [];
        $header = null;

        if (($handle = fopen($filepath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if ($has_header && $header === null) {
                    $header = array_map('trim', $row);

                    continue;
                }

                if ($header !== null) {
                    $row = array_pad($row, count($header), null);
                    $data[] = array_combine($header, array_slice($row, 0, count($header)));
                } else {
                    $data[] = $row;
                }
            }
            fclose($handle);
        }

        return $has_header ? [$data, $header ?? []] : $data;
    }
}

if (! function_exists('arrayToCsv')) {
    /**
     * Convert an array of associative arrays to CSV format or force file download.
     */
    function arrayToCsv(array $array, string $download = ''): ?string
    {
        if (empty($array)) {
            return null;
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return null;
        }

        fputcsv($handle, array_keys(reset($array)));

        foreach ($array as $row) {
            fputcsv($handle, (array) $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($download !== '') {
            $filename = str_ends_with($download, '.csv') ? $download : "{$download}.csv";
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            echo $csv;
            exit(0);
        }

        return $csv !== false ? $csv : null;
    }
}

if (! function_exists('validateColumnsExist')) {
    /**
     * Verify required columns exist in given CSV headers.
     *
     * @param  array<string>|null  $headers
     * @param  array<string>  $columns
     * @return array<string> Missing columns
     */
    function validateColumnsExist(?array $headers, array $columns): array
    {
        $headers = $headers ?? [];
        $normalizedHeaders = array_map(static fn ($h) => strtolower(trim((string) $h)), $headers);
        $missing = [];

        foreach ($columns as $col) {
            if (! in_array(strtolower(trim($col)), $normalizedHeaders, true)) {
                $missing[] = $col;
            }
        }

        return $missing;
    }
}

if (! function_exists('arrayValue')) {
    /**
     * Safely read array key by reference with default fallback.
     */
    function arrayValue(mixed &$input, mixed $default = ''): mixed
    {
        return $input ?? $default;
    }
}

if (! function_exists('sanitizeInput')) {
    /**
     * Recursively sanitize input string or array with htmlspecialchars.
     */
    function sanitizeInput(string|array|null $input): string|array
    {
        if ($input === null) {
            return '';
        }

        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('encodeInput')) {
    function encodeInput(string $string): string
    {
        return base64_encode(urlencode($string));
    }
}

if (! function_exists('decodeInput')) {
    function decodeInput(string $string): string
    {
        return urldecode(base64_decode($string) ?: '');
    }
}

if (! function_exists('generateCode')) {
    /**
     * Format a string/id with prefix and padding.
     */
    function generateCode(
        string|int $string,
        string $prefix = '',
        int $length = 3,
        string $pad_string = '0',
        int $pad_type = STR_PAD_LEFT
    ): string {
        return $prefix.str_pad((string) $string, $length, $pad_string, $pad_type);
    }
}

if (! function_exists('implodeIds')) {
    /**
     * Sanitize and format an array of integer IDs into a comma-separated string.
     *
     * @param  array<int|string>  $ids
     */
    function implodeIds(array $ids): string
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return ! empty($clean) ? implode(',', $clean) : '0';
    }
}

if (! function_exists('formatAddressFromArray')) {
    /**
     * Format an address string cleanly from array fields.
     */
    function formatAddressFromArray(array $data): string
    {
        $parts = [
            $data['address_1'] ?? $data['address'] ?? null,
            $data['address_2'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['country'] ?? null,
            $data['postal_code'] ?? $data['pincode'] ?? null,
        ];

        return implode(', ', array_filter(array_map('trim', array_filter($parts))));
    }
}

if (! function_exists('isValidEmail')) {
    function isValidEmail(?string $email): bool
    {
        return ! empty($email) && (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (! function_exists('isValidDate')) {
    function isValidDate(mixed $datetime): bool
    {
        if (empty($datetime) || ! is_string($datetime)) {
            return false;
        }

        return strtotime($datetime) !== false;
    }
}

if (! function_exists('formatDate')) {
    /**
     * Safely format a date/time string.
     */
    function formatDate(mixed $datetime, string $format = 'j M, Y \a\t h:i A'): string
    {
        if (empty($datetime)) {
            return '';
        }
        $ts = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);

        return $ts !== false ? date($format, $ts) : '';
    }
}

if (! function_exists('formatTimeAgo')) {
    function formatTimeAgo(DateTimeInterface|string|int|null $datetime): string
    {
        if (empty($datetime)) {
            return 'N/A';
        }

        if ($datetime instanceof DateTimeInterface) {
            $date = DateTimeImmutable::createFromInterface($datetime);
            $timestamp = $date->getTimestamp();
        } elseif (is_numeric($datetime)) {
            $timestamp = (int) $datetime;
            $date = (new DateTimeImmutable)->setTimestamp($timestamp);
        } else {
            try {
                $date = new DateTimeImmutable((string) $datetime);
                $timestamp = $date->getTimestamp();
            } catch (Throwable) {
                return 'N/A';
            }
        }

        $diff = time() - $timestamp;

        if ($diff < 0) {
            $diff = abs($diff);

            return match (true) {
                $diff < 45 => 'Just now',
                $diff < 90 => 'in 1 min',
                $diff < 3600 => ($mins = (int) floor($diff / 60)) <= 1 ? 'in 1 min' : "in {$mins} mins",
                $diff < 86400 => ($hours = (int) floor($diff / 3600)) <= 1 ? 'in 1 hr' : "in {$hours} hrs",
                $diff < 172800 => 'Tomorrow '.$date->format('g:i A'),
                $diff < 2592000 => ($days = (int) floor($diff / 86400)) <= 1 ? 'in 1 day' : "in {$days} days",
                default => $date->format('M j, Y'),
            };
        }

        return match (true) {
            $diff < 45 => 'Just now',
            $diff < 90 => '1 min ago',
            $diff < 3600 => ($mins = (int) floor($diff / 60)) <= 1 ? '1 min ago' : "{$mins} mins ago",
            $diff < 86400 => ($hours = (int) floor($diff / 3600)) === 1 ? '1 hr ago' : "{$hours} hrs ago",
            $diff < 172800 => 'Yesterday '.$date->format('g:i A'),
            $diff < 2592000 => ($days = (int) floor($diff / 86400)) === 1 ? '1 day ago' : "{$days} days ago",
            default => $date->format('M j, Y'),
        };
    }
}

if (! function_exists('formatAmount')) {
    function formatAmount(mixed $amount, int $decimals = 4, string $decimal_separator = '.', string $thousands_separator = ''): string
    {
        return number_format((float) ($amount ?? 0), $decimals, $decimal_separator, $thousands_separator);
    }
}

if (! function_exists('displayAmount')) {
    function displayAmount(mixed $amount, int $precision = 2): string
    {
        if (defined('APP_CURRENCY') && APP_CURRENCY) {
            $locale = defined('APP_LOCALE') ? APP_LOCALE : null;

            return (new Zend_Currency([
                'value' => $amount ?? 0,
                'symbol' => APP_CURRENCY,
                'precision' => $precision,
            ], $locale))->toString();
        }

        return number_format((float) ($amount ?? 0), $precision, '.', ',');
    }
}

if (! function_exists('printAmount')) {
    function printAmount(mixed $amount, int $precision = 2): string
    {
        return formatAmount($amount, $precision);
    }
}

if (! function_exists('spellSeconds')) {
    /**
     * Convert seconds to human-readable interval string (e.g. 2 hours, 15 minutes).
     */
    function spellSeconds(int $seconds, int $parts = 4, string $join = ', '): string
    {
        if ($seconds <= 0) {
            return '0 seconds';
        }

        $units = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        $output = [];
        foreach ($units as $name => $divisor) {
            if ($seconds >= $divisor) {
                $qty = (int) floor($seconds / $divisor);
                $seconds %= $divisor;
                $output[] = "{$qty} ".($qty === 1 ? $name : "{$name}s");
                if (count($output) >= $parts) {
                    break;
                }
            }
        }

        return ! empty($output) ? implode($join, $output) : '0 seconds';
    }
}

if (! function_exists('convertChunkToWords')) {
    function convertChunkToWords(int $num): string
    {
        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen',
        ];
        $tens = [
            '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
        ];
        $scales = ['', 'Thousand', 'Million', 'Billion', 'Trillion', 'Quadrillion'];

        if ($num === 0) {
            return '';
        }

        $chunks = [];
        while ($num > 0) {
            $chunks[] = $num % 1000;
            $num = intdiv($num, 1000);
        }

        $stringParts = [];
        foreach ($chunks as $index => $chunk) {
            if ($chunk === 0) {
                continue;
            }

            $chunkWords = '';
            $hundreds = intdiv($chunk, 100);
            $remainder = $chunk % 100;

            if ($hundreds > 0) {
                $chunkWords .= $ones[$hundreds].' Hundred';
                if ($remainder > 0) {
                    $chunkWords .= ' ';
                }
            }

            if ($remainder > 0) {
                if ($remainder < 20) {
                    $chunkWords .= $ones[$remainder];
                } else {
                    $chunkWords .= $tens[intdiv($remainder, 10)];
                    $onesDigit = $remainder % 10;
                    if ($onesDigit > 0) {
                        $chunkWords .= ' '.$ones[$onesDigit];
                    }
                }
            }

            if ($index > 0 && isset($scales[$index])) {
                $chunkWords .= ' '.$scales[$index];
            }

            $stringParts[] = $chunkWords;
        }

        return implode(' ', array_reverse($stringParts));
    }
}

if (! function_exists('SpellAmount')) {
    function SpellAmount(float|int|string $amount): string
    {
        $formatted = number_format((float) $amount, 2, '.', '');
        [$whole, $decimal] = explode('.', $formatted);

        $wholeNum = (int) $whole;
        if ($wholeNum === 0) {
            $result = 'Zero';
        } else {
            $result = convertChunkToWords($wholeNum);
        }

        $decNum = (int) $decimal;
        if ($decNum > 0) {
            $result .= ' and '.convertChunkToWords($decNum);
        }

        return trim(preg_replace('/\s+/', ' ', $result));
    }
}

if (! function_exists('formatBytes')) {
    /**
     * Format bytes into a human-readable string (e.g. 1.25 MB).
     */
    function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $bytes /= (1024 ** $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}

if (! function_exists('password_bcrypt')) {
    function password_bcrypt(string $password, int $cost = 12): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}

if (! function_exists('config')) {
    /**
     * Retrieve nested configuration value from Zend_Registry.
     */
    function config(string $key, mixed $default = null): mixed
    {
        try {
            if (! Zend_Registry::isRegistered('config')) {
                return $default;
            }
            $config = Zend_Registry::get('config');
            $data = is_object($config) && method_exists($config, 'toArray') ? $config->toArray() : (array) $config;

            foreach (explode('.', $key) as $segment) {
                if (is_array($data) && array_key_exists($segment, $data)) {
                    $data = $data[$segment];
                } else {
                    return $default;
                }
            }

            return $data;
        } catch (Throwable) {
            return $default;
        }
    }
}

if (! function_exists('blank')) {
    /**
     * Determine if given value is "blank".
     */
    function blank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_numeric($value) || is_bool($value)) {
            return false;
        }
        if ($value instanceof Countable) {
            return count($value) === 0;
        }
        if ($value instanceof Stringable) {
            return trim((string) $value) === '';
        }

        return empty($value);
    }
}

if (! function_exists('filled')) {
    function filled(mixed $value): bool
    {
        return ! blank($value);
    }
}

if (! function_exists('data_get')) {
    /**
     * Safely retrieve a value from an array or object using dot-notation.
     */
    function data_get(mixed $target, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }

        $key = is_int($key) ? (string) $key : $key;

        foreach (explode('.', $key) as $segment) {
            if (is_array($target)) {
                if (array_key_exists($segment, $target)) {
                    $target = $target[$segment];
                } else {
                    return $default instanceof Closure ? $default() : $default;
                }
            } elseif (is_object($target)) {
                if (isset($target->{$segment})) {
                    $target = $target->{$segment};
                } elseif (method_exists($target, $segment)) {
                    $target = $target->{$segment}();
                } else {
                    return $default instanceof Closure ? $default() : $default;
                }
            } else {
                return $default instanceof Closure ? $default() : $default;
            }
        }

        return $target;
    }
}

if (! function_exists('data_set')) {
    /**
     * Set an item on an array or object using dot notation.
     *
     * @param  string|array<string|int>|int|null  $key
     */
    function data_set(mixed &$target, string|array|int|null $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', (string) $key);

        if (empty($segments)) {
            return $target;
        }

        $current = &$target;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if ($segment === '*') {
                if (! is_array($current)) {
                    return $target;
                }

                foreach ($current as &$inner) {
                    data_set($inner, $segments, $value, $overwrite);
                }

                return $target;
            }

            if (is_array($current)) {
                if (! array_key_exists($segment, $current) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            } elseif (is_object($current)) {
                if (! isset($current->{$segment}) || ! is_object($current->{$segment})) {
                    $current->{$segment} = new stdClass;
                }
                $current = &$current->{$segment};
            } else {
                $current = [];
                $current = &$current[$segment];
            }
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (is_array($current)) {
                foreach ($current as &$inner) {
                    if ($overwrite || ! isset($inner)) {
                        $inner = $value;
                    }
                }
            }

            return $target;
        }

        if (is_array($current)) {
            if ($overwrite || ! array_key_exists($segment, $current)) {
                $current[$segment] = $value;
            }
        } elseif (is_object($current)) {
            if ($overwrite || ! isset($current->{$segment})) {
                $current->{$segment} = $value;
            }
        }

        return $target;
    }
}

if (! function_exists('array_only')) {
    /**
     * Get a subset of the items from the given array.
     *
     * @param  array<string, mixed>  $array
     * @param  array<string>|string  $keys
     * @return array<string, mixed>
     */
    function array_only(array $array, array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : [$keys];

        return array_intersect_key($array, array_flip($keys));
    }
}

if (! function_exists('array_except')) {
    /**
     * Get all of the given array except for a specified array of keys.
     *
     * @param  array<string, mixed>  $array
     * @param  array<string>|string  $keys
     * @return array<string, mixed>
     */
    function array_except(array $array, array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : [$keys];

        return array_diff_key($array, array_flip($keys));
    }
}

if (! function_exists('array_wrap')) {
    /**
     * If the given value is not an array and not null, wrap it in one.
     */
    function array_wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}

if (! function_exists('array_first')) {
    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param  array<mixed>  $array
     */
    function array_first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($array)) {
                return $default instanceof Closure ? $default() : $default;
            }
            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default instanceof Closure ? $default() : $default;
    }
}

if (! function_exists('array_last')) {
    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param  array<mixed>  $array
     */
    function array_last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        return array_first(array_reverse($array, true), $callback, $default);
    }
}

if (! function_exists('array_has')) {
    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param  array<string, mixed>  $array
     */
    function array_has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        $segments = explode('.', $key);
        $current = $array;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }
}

if (! function_exists('array_forget')) {
    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param  array<string, mixed>  $array
     * @param  array<string>|string  $keys
     */
    function array_forget(array &$array, array|string $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            $segments = explode('.', $key);
            $current = &$array;

            while (count($segments) > 1) {
                $segment = array_shift($segments);
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    continue 2;
                }
                $current = &$current[$segment];
            }

            unset($current[array_shift($segments)]);
        }
    }
}

if (! function_exists('array_pull')) {
    /**
     * Get a value from the array, and remove it.
     *
     * @param  array<string, mixed>  $array
     */
    function array_pull(array &$array, string $key, mixed $default = null): mixed
    {
        $value = data_get($array, $key, $default);
        array_forget($array, $key);

        return $value;
    }
}

if (! function_exists('array_key_by')) {
    /**
     * Key an associative array by a field or callback.
     *
     * @param  array<mixed>  $array
     */
    function array_key_by(array $array, string|callable $key): array
    {
        $results = [];

        foreach ($array as $item) {
            $resolvedKey = is_callable($key) ? $key($item) : data_get($item, $key);
            if ($resolvedKey !== null) {
                $results[(string) $resolvedKey] = $item;
            }
        }

        return $results;
    }
}

if (! function_exists('csrf_token')) {
    /**
     * Get or generate the current CSRF token from the session.
     */
    function csrf_token(): string
    {
        return ZF1\Session\Session::token();
    }
}

if (! function_exists('csrf_field')) {
    /**
     * Generate a CSRF token hidden HTML form input tag.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="'.htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">';
    }
}

if (! function_exists('csrf_verify')) {
    /**
     * Verify whether the given token matches the active CSRF token.
     */
    function csrf_verify(?string $token = null): bool
    {
        $token = $token ?: ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
        $sessionToken = ZF1\Session\Session::get('_token');

        if (empty($token) || empty($sessionToken)) {
            return false;
        }

        return hash_equals((string) $sessionToken, (string) $token);
    }
}

if (! function_exists('request_wants_json')) {
    /**
     * Determine if current request expects a JSON response.
     */
    function request_wants_json(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
    }
}

if (! function_exists('request_bearer_token')) {
    /**
     * Get the bearer token from the request Authorization header.
     */
    function request_bearer_token(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        if ($header !== '' && preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

if (! function_exists('discord_notify')) {
    /**
     * Send an alert notification to Discord.
     *
     * @param  string  $message  Main message text
     * @param  string  $level  Notification level ('info', 'success', 'warning', 'error')
     * @param  array<string, mixed>  $fields  Key-value fields displayed in Discord embed
     * @param  string|null  $title  Optional title
     */
    function discord_notify(
        string $message,
        string $level = 'info',
        array $fields = [],
        ?string $title = null
    ): bool {
        return ZF1\Logging\DiscordNotifier::send($message, $level, $fields, $title);
    }
}

if (! function_exists('is_framework_path')) {
    /**
     * Determine if a given file path or trace frame belongs to the framework, vendor, or runtime.
     */
    function is_framework_path(string $filePath): bool
    {
        if ($filePath === '' || $filePath === 'unknown' || $filePath === '[internal function]' || $filePath === '{main}') {
            return true;
        }

        $normalized = str_replace('\\', '/', $filePath);

        $frameworkPatterns = [
            '/vendor/',
            '/packages/zf1/',
            'shubhamc4/zf1',
            '/library/Zend/',
            '/library/ZF1/',
            '/Zend/',
            '/ZF1/',
            '/valet/',
            'Herd.app',
            '/public/index.php',
            '/public/zf',
            '/bin/zf',
        ];

        foreach ($frameworkPatterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('formatCleanTrace')) {
    /**
     * Filter out vendor/framework frames from stack traces and format application frames.
     */
    function formatCleanTrace(Throwable|string $trace, bool $shortenAppPath = false): string
    {
        $rawTrace = $trace instanceof Throwable ? $trace->getTraceAsString() : (string) $trace;
        if (trim($rawTrace) === '') {
            return '';
        }

        $baseDir = base_path().DIRECTORY_SEPARATOR;

        $lines = explode("\n", $rawTrace);
        $appFrames = [];
        $hiddenCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (is_framework_path($trimmed)) {
                $hiddenCount++;

                continue;
            }

            if (! $shortenAppPath && preg_match('/^(#\d+\s+)([^(]+)(\(\d+\):?.*)$/', $line, $matches)) {
                $filePart = trim($matches[2]);
                $isAbsolute = str_starts_with($filePart, '/')
                    || str_starts_with($filePart, '\\')
                    || (bool) preg_match('/^[a-zA-Z]:[\\\\\/]/', $filePart);

                if (! $isAbsolute && $filePart !== '[internal function]' && $filePart !== '{main}' && function_exists('base_path')) {
                    $line = $matches[1].base_path($filePart).$matches[3];
                }
            }

            if ($shortenAppPath && $baseDir !== '' && str_contains($line, $baseDir)) {
                $line = str_replace($baseDir, '', $line);
            }

            $appFrames[] = $line;
        }

        if (empty($appFrames)) {
            $preview = array_slice($lines, 0, 4);
            if (! $shortenAppPath && function_exists('base_path')) {
                $preview = array_map(static function ($l) {
                    if (preg_match('/^(#\d+\s+)([^(]+)(\(\d+\):?.*)$/', $l, $matches)) {
                        $filePart = trim($matches[2]);
                        $isAbsolute = str_starts_with($filePart, '/')
                            || str_starts_with($filePart, '\\')
                            || (bool) preg_match('/^[a-zA-Z]:[\\\\\/]/', $filePart);

                        if (! $isAbsolute && $filePart !== '[internal function]' && $filePart !== '{main}') {
                            return $matches[1].base_path($filePart).$matches[3];
                        }
                    }

                    return $l;
                }, $preview);
            } elseif ($shortenAppPath && $baseDir !== '') {
                $preview = array_map(static fn ($l) => str_replace($baseDir, '', $l), $preview);
            }

            return implode("\n", $preview);
        }

        $result = implode("\n", array_slice($appFrames, 0, 10));
        if ($hiddenCount > 0) {
            $result .= sprintf("\n... [+%d vendor/framework frames hidden]", $hiddenCount);
        }

        return $result;
    }
}

if (! function_exists('resolveAppOrigin')) {
    /**
     * Find the highest-level application file and line from an exception trace or error array.
     *
     * @param  Throwable|array{file?: string, line?: int}  $source
     * @return array{file: string, line: int}
     */
    function resolveAppOrigin(Throwable|array $source): array
    {
        $toExactPath = static function (string $filePath): string {
            if ($filePath === '' || $filePath === 'unknown') {
                return $filePath;
            }

            $isAbsolute = str_starts_with($filePath, '/')
                || str_starts_with($filePath, '\\')
                || (bool) preg_match('/^[a-zA-Z]:[\\\\\/]/', $filePath);

            if (! $isAbsolute && function_exists('base_path')) {
                return base_path($filePath);
            }

            return $filePath;
        };

        if ($source instanceof Throwable) {
            if (class_exists('Zend_Controller_Front', false)) {
                try {
                    $front = Zend_Controller_Front::getInstance();
                    $resp = $front ? $front->getResponse() : null;
                    if ($resp !== null && $resp->isException()) {
                        $original = $resp->getException();
                        if (! empty($original[0]) && $original[0] !== $source) {
                            $source = $original[0];
                        }
                    }
                } catch (Throwable) {
                    // Ignore
                }
            }

            while ($source->getPrevious() !== null) {
                $class = get_class($source);
                if (str_contains($class, 'Zend_') || $class === 'Exception' || $class === 'ErrorException') {
                    $source = $source->getPrevious();
                } else {
                    break;
                }
            }

            $thrownFile = (string) $source->getFile();
            $thrownLine = (int) $source->getLine();

            $trace = $source->getTrace();
            $appDir = function_exists('app_path') ? str_replace('\\', '/', app_path()) : '';
            $baseDir = function_exists('base_path') ? str_replace('\\', '/', base_path()) : '';

            if ($appDir !== '') {
                $normThrown = str_replace('\\', '/', $thrownFile);
                if (str_starts_with($normThrown, $appDir) && (str_contains($normThrown, '/controllers/') || str_ends_with($normThrown, 'Controller.php'))) {
                    return [
                        'file' => $toExactPath($thrownFile),
                        'line' => $thrownLine,
                        'thrown_file' => $toExactPath($thrownFile),
                        'thrown_line' => $thrownLine,
                    ];
                }

                foreach ($trace as $frame) {
                    if (! empty($frame['file']) && ! empty($frame['line'])) {
                        $normFrameFile = str_replace('\\', '/', (string) $frame['file']);
                        if (str_starts_with($normFrameFile, $appDir)
                            && (str_contains($normFrameFile, '/controllers/') || str_ends_with($normFrameFile, 'Controller.php'))
                            && ! is_framework_path($normFrameFile)
                        ) {
                            return [
                                'file' => $toExactPath($frame['file']),
                                'line' => (int) $frame['line'],
                                'thrown_file' => $toExactPath($thrownFile),
                                'thrown_line' => $thrownLine,
                            ];
                        }
                    }
                }
            }

            if (! is_framework_path($thrownFile)) {
                return [
                    'file' => $toExactPath($thrownFile),
                    'line' => $thrownLine,
                    'thrown_file' => $toExactPath($thrownFile),
                    'thrown_line' => $thrownLine,
                ];
            }

            if ($appDir !== '') {
                foreach ($trace as $frame) {
                    if (! empty($frame['file']) && ! empty($frame['line'])) {
                        $normFrameFile = str_replace('\\', '/', (string) $frame['file']);
                        if (str_starts_with($normFrameFile, $appDir) && ! is_framework_path($normFrameFile)) {
                            return [
                                'file' => $toExactPath($frame['file']),
                                'line' => (int) $frame['line'],
                                'thrown_file' => $toExactPath($thrownFile),
                                'thrown_line' => $thrownLine,
                            ];
                        }
                    }
                }
            }

            if ($baseDir !== '') {
                foreach ($trace as $frame) {
                    if (! empty($frame['file']) && ! empty($frame['line'])) {
                        $normFrameFile = str_replace('\\', '/', (string) $frame['file']);
                        if (str_starts_with($normFrameFile, $baseDir) && ! is_framework_path($normFrameFile)) {
                            return [
                                'file' => $toExactPath($frame['file']),
                                'line' => (int) $frame['line'],
                                'thrown_file' => $toExactPath($thrownFile),
                                'thrown_line' => $thrownLine,
                            ];
                        }
                    }
                }
            }

            foreach ($trace as $frame) {
                if (! empty($frame['file']) && ! empty($frame['line'])) {
                    if (! is_framework_path((string) $frame['file'])) {
                        return [
                            'file' => $toExactPath($frame['file']),
                            'line' => (int) $frame['line'],
                            'thrown_file' => $toExactPath($thrownFile),
                            'thrown_line' => $thrownLine,
                        ];
                    }
                }
            }

            return [
                'file' => $toExactPath($thrownFile),
                'line' => $thrownLine,
                'thrown_file' => $toExactPath($thrownFile),
                'thrown_line' => $thrownLine,
            ];
        }

        $rawFile = (string) ($source['file'] ?? $source['File'] ?? 'unknown');
        $rawLine = (int) ($source['line'] ?? $source['Line'] ?? 0);

        return [
            'file' => $toExactPath($rawFile),
            'line' => $rawLine,
        ];
    }
}

if (! function_exists('dispatch')) {
    /**
     * Dispatch a background job to the queue.
     *
     * @param  string|object  $job  Job class name or Job instance
     * @param  array<string, mixed>  $payload  Arguments when passing class-string
     * @param  string  $queue  Queue name
     * @param  int  $delay  Delay in seconds
     * @return int|bool Inserted job ID or boolean sync result
     */
    function dispatch(string|object $job, array $payload = [], string $queue = 'default', int $delay = 0): mixed
    {
        if (is_object($job)) {
            if (method_exists($job, 'dispatch')) {
                return $job->dispatch();
            }
            $jobClass = get_class($job);
            $jobPayload = get_object_vars($job);

            return ZF1\Queue\Queue::push($jobClass, $jobPayload, $queue, $delay);
        }

        if (is_subclass_of($job, ZF1\Queue\Job::class)) {
            return $job::dispatch($payload, $queue, $delay);
        }

        return ZF1\Queue\Queue::push($job, $payload, $queue, $delay);
    }
}

if (! function_exists('abort')) {
    /**
     * Throw an HttpException with the given status code, message, and headers.
     *
     * Examples:
     *   abort(404);
     *   abort(403, 'Unauthorized access.');
     *   abort(429, 'Too many requests.', ['Retry-After' => '60']);
     *
     * @param  array<string, string>  $headers
     *
     * @throws ZF1\Exceptions\HttpException
     */
    function abort(int|Throwable $code, string $message = '', array $headers = []): never
    {
        if ($code instanceof Throwable) {
            throw $code;
        }

        foreach ($headers as $header => $value) {
            if (! headers_sent()) {
                header("{$header}: {$value}");
            }
        }

        throw match ($code) {
            400 => new ZF1\Exceptions\BadRequestHttpException($message, null, $headers),
            401 => new ZF1\Exceptions\UnauthorizedHttpException($message, null, $headers),
            403 => new ZF1\Exceptions\AccessDeniedHttpException($message, null, $headers),
            404 => new ZF1\Exceptions\NotFoundHttpException($message, null, $headers),
            405 => new ZF1\Exceptions\MethodNotAllowedHttpException($message, null, $headers),
            429 => new ZF1\Exceptions\TooManyRequestsHttpException($message, null, $headers),
            503 => new ZF1\Exceptions\ServiceUnavailableHttpException($message, null, $headers),
            default => new ZF1\Exceptions\HttpException($code, $message, null, $headers),
        };
    }
}

if (! function_exists('abort_if')) {
    /**
     * Throw an HttpException if the given condition is true.
     *
     * Examples:
     *   abort_if(! $user, 404);
     *   abort_if($user->is_banned, 403, 'Account suspended.');
     *   abort_if(fn() => $this->isRateLimited(), 429);
     *
     * @param  array<string, string>  $headers
     *
     * @throws ZF1\Exceptions\HttpException
     */
    function abort_if(mixed $boolean, int|Throwable $code, string $message = '', array $headers = []): void
    {
        if ($boolean instanceof Closure) {
            $boolean = $boolean();
        }

        if ($boolean) {
            abort($code, $message, $headers);
        }
    }
}

if (! function_exists('abort_unless')) {
    /**
     * Throw an HttpException unless the given condition is true.
     *
     * Examples:
     *   abort_unless($user, 404);
     *   abort_unless($user->hasPermission('admin'), 403, 'Access denied.');
     *
     * @param  array<string, string>  $headers
     *
     * @throws ZF1\Exceptions\HttpException
     */
    function abort_unless(mixed $boolean, int|Throwable $code, string $message = '', array $headers = []): void
    {
        if ($boolean instanceof Closure) {
            $boolean = $boolean();
        }

        if (! $boolean) {
            abort($code, $message, $headers);
        }
    }
}

if (! function_exists('report')) {
    /**
     * Report an exception to the logger without terminating execution.
     */
    function report(Throwable|string $exception): void
    {
        if (is_string($exception)) {
            $exception = new RuntimeException($exception);
        }

        if (class_exists(ZF1\Logging\UnifiedErrorHandler::class)) {
            ZF1\Logging\UnifiedErrorHandler::logException($exception);
        }
    }
}

if (! function_exists('rescue')) {
    /**
     * Catch an exception and return a fallback value.
     *
     * @template TReturn
     * @template TFallback
     *
     * @param  callable(): TReturn  $callback
     * @param  (callable(Throwable): TFallback)|TFallback  $rescue
     * @param  bool|callable(Throwable): bool  $report
     * @return TReturn|TFallback
     */
    function rescue(callable $callback, mixed $rescue = null, mixed $report = true): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $shouldReport = is_callable($report) ? $report($e) : (bool) $report;
            if ($shouldReport && function_exists('report')) {
                report($e);
            }

            return is_callable($rescue) ? $rescue($e) : $rescue;
        }
    }
}
