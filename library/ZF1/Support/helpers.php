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

if (! function_exists('getIpAddress')) {
    /**
     * Get client IP address resolving proxies.
     */
    function getIpAddress(): string
    {
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            return (string) $_SERVER['HTTP_CLIENT_IP'];
        }
        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);

            return trim($parts[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
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
     */
    function parseCSV(string $filepath, bool $has_header = true): array
    {
        if (! file_exists($filepath) || ! is_readable($filepath)) {
            return [];
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

        return $data;
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

        // Write header
        fputcsv($handle, array_keys(reset($array)));

        // Write data
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
     * @param  array<string>  $headers
     * @param  array<string>  $columns
     * @return array<string> Missing columns
     */
    function validateColumnsExist(array $headers, array $columns): array
    {
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

if (! function_exists('formatAmount')) {
    function formatAmount(mixed $amount, int $decimals = 4, string $decimal_separator = '.', string $thousands_separator = ''): string
    {
        return number_format((float) ($amount ?? 0), $decimals, $decimal_separator, $thousands_separator);
    }
}

if (! function_exists('displayAmount')) {
    function displayAmount(mixed $amount, int $precision = 2): string
    {
        return number_format((float) ($amount ?? 0), $precision, '.', ',');
    }
}

if (! function_exists('printAmount')) {
    function printAmount(mixed $amount, int $precision = 2): string
    {
        return displayAmount($amount, $precision);
    }
}

if (! function_exists('SpellAmount')) {
    /**
     * Convert currency number into English words.
     */
    function SpellAmount(float|int|string $amount): string
    {
        $amount = (float) $amount;
        $ones = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        ];
        $tens = [
            1 => 'Ten', 2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];
        $hundreds = ['Hundred', 'Thousand', 'Million', 'Billion', 'Trillion', 'Quadrillion'];

        $num_arr = explode('.', number_format($amount, 2, '.', ','));
        $decnum = (int) ($num_arr[1] ?? 0);
        $whole_arr = array_reverse(explode(',', $num_arr[0]));
        krsort($whole_arr);

        $string = '';
        foreach ($whole_arr as $key => $i) {
            $i = (int) $i;
            if ($i < 20) {
                $string .= $ones[$i];
            } elseif ($i < 100) {
                $string .= $tens[(int) substr((string) $i, 0, 1)];
                $string .= ' '.$ones[(int) substr((string) $i, 1, 1)];
            } else {
                $string .= $ones[(int) substr((string) $i, 0, 1)].' '.$hundreds[0];
                $string .= ' '.$tens[(int) substr((string) $i, 1, 1)];
                $string .= ' '.$ones[(int) substr((string) $i, 2, 1)];
            }

            if ($key > 0 && $i > 0) {
                $string .= ' '.$hundreds[$key].' ';
            }
        }

        if ($decnum > 0) {
            $string .= ' and ';
            if ($decnum < 20) {
                $string .= $ones[$decnum];
            } elseif ($decnum < 100) {
                $string .= $tens[(int) substr((string) $decnum, 0, 1)];
                $string .= ' '.$ones[(int) substr((string) $decnum, 1, 1)];
            }
        }

        return trim(preg_replace('/\s+/', ' ', $string));
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
        $vendorDir = base_path('vendor').DIRECTORY_SEPARATOR;
        $libraryZfDir = base_path('library/Zend');

        $lines = explode("\n", $rawTrace);
        $appFrames = [];
        $hiddenCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $isFramework = str_contains($line, $vendorDir)
                || str_contains($line, $libraryZfDir)
                || str_contains($line, 'shubhamc4/zf1')
                || str_contains($line, 'Zend/');

            if ($isFramework) {
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

        $vendorDir = base_path('vendor').DIRECTORY_SEPARATOR;
        $libraryZfDir = base_path('library/Zend');

        if ($source instanceof Throwable) {
            $file = (string) $source->getFile();
            $line = (int) $source->getLine();

            $isFramework = str_contains($file, $vendorDir)
                || str_contains($file, $libraryZfDir)
                || str_contains($file, 'shubhamc4/zf1')
                || str_contains($file, 'Zend/');

            if (! $isFramework) {
                return [
                    'file' => $toExactPath($file),
                    'line' => $line,
                ];
            }

            foreach ($source->getTrace() as $frame) {
                if (! empty($frame['file']) && ! empty($frame['line'])) {
                    $frameFile = (string) $frame['file'];
                    $frameLine = (int) $frame['line'];

                    $isVendor = str_contains($frameFile, $vendorDir)
                        || str_contains($frameFile, $libraryZfDir)
                        || str_contains($frameFile, 'shubhamc4/zf1')
                        || str_contains($frameFile, 'Zend/');

                    if (! $isVendor) {
                        return [
                            'file' => $toExactPath($frameFile),
                            'line' => $frameLine,
                        ];
                    }
                }
            }

            return [
                'file' => $toExactPath($file),
                'line' => $line,
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
