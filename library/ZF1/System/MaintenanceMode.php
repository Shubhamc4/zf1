<?php

declare(strict_types=1);

namespace ZF1\System;

/**
 * Maintenance Mode Management
 */
final class MaintenanceMode
{
    public const DOWN_FILE = 'storage/down';

    public static function getCookieName(): string
    {
        $prefix = (string) env('SESSION_NAME', defined('SESSION_NAME') ? (string) SESSION_NAME : 'ZF1');

        return strtolower($prefix).'_maintenance_bypass';
    }

    public static function getAppName(): string
    {
        return (string) env('APP_NAME', defined('APP_NAME') ? (string) APP_NAME : 'ZF1');
    }

    /**
     * Check if application is in maintenance mode and handle response.
     */
    public static function check(): void
    {
        $filePath = self::getFilePath();

        if (! file_exists($filePath)) {
            if (isset($_COOKIE[self::getCookieName()])) {
                self::clearBypassCookie();
            }

            return;
        }

        if (class_exists(\ZF1\Console\WebConsole::class)
            && \ZF1\Console\WebConsole::isAllowedDuringMaintenance()
        ) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            if (\ZF1\Console\WebConsole::matchesPath($uri)) {
                return;
            }
        }

        $content = file_get_contents($filePath);
        $data = json_decode((string) $content, true) ?: [];

        $secret = ! empty($data['secret']) ? (string) $data['secret'] : null;
        $cycleToken = ! empty($data['token']) ? (string) $data['token'] : null;

        if ($cycleToken !== null && ! empty($_COOKIE[self::getCookieName()])) {
            if (hash_equals($cycleToken, (string) $_COOKIE[self::getCookieName()])) {
                return;
            }
        }

        $errorMessage = null;

        if ($secret !== null && isset($_POST['maintenance_secret'])) {
            $submitted = trim((string) $_POST['maintenance_secret']);
            if (hash_equals($secret, $submitted)) {
                self::setBypassCookie($cycleToken ?? $secret);

                $redirectUrl = self::resolveSafeRedirect($_POST['redirect'] ?? null);
                header("Location: {$redirectUrl}");
                exit(0);
            }
            $errorMessage = 'Invalid maintenance bypass key. Please verify and try again.';
        }

        if ($secret !== null && isset($_GET['secret'])) {
            $paramSecret = trim((string) $_GET['secret']);
            if (hash_equals($secret, $paramSecret)) {
                self::setBypassCookie($cycleToken ?? $secret);

                $cleanUrl = self::removeQueryParam($_SERVER['REQUEST_URI'] ?? '/', 'secret');
                header("Location: {$cleanUrl}");
                exit(0);
            }
        }

        if ($secret !== null && ! empty($_SERVER['REQUEST_URI'])) {
            $uriPath = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
            if ($uriPath === $secret) {
                self::setBypassCookie($cycleToken ?? $secret);

                $redirectUrl = self::resolveSafeRedirect($_GET['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? null));
                header("Location: {$redirectUrl}");
                exit(0);
            }
        }

        $retryAfter = max(5, (int) ($data['retry'] ?? 60));
        $appName = self::getAppName();
        $message = ! empty($data['message'])
            ? $data['message']
            : "We are currently performing scheduled maintenance and updates. {$appName} will be back online shortly.";

        http_response_code(503);
        header('HTTP/1.1 503 Service Unavailable');
        header('Status: 503 Service Unavailable');
        header("Retry-After: {$retryAfter}");
        header('Cache-Control: no-cache, private, no-store, must-revalidate, max-stale=0, post-check=0, pre-check=0');

        $isJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api/'));

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 503,
                'error' => 'Service Unavailable',
                'message' => $message,
                'maintenance' => true,
                'retry_after' => $retryAfter,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit(0);
        }

        $template = ! empty($data['template']) ? (string) $data['template'] : null;
        $customView = self::resolveCustomView($template);

        if ($customView !== null) {
            header('Content-Type: text/html; charset=utf-8');
            $hasSecret = $secret !== null;
            $code = 503;
            $title = 'Maintenance in Progress';
            $appName = self::getAppName();
            $homeUrl = function_exists('app_url') ? app_url('/') : '/';
            $theme = class_exists(\ZF1\View\ErrorRenderer::class) ? \ZF1\View\ErrorRenderer::theme() : 'auto';
            $currentUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
            require $customView;
            exit(0);
        }

        self::renderDefaultHtml($message, $retryAfter, $secret !== null, $errorMessage);
        exit(0);
    }

    /**
     * Put the application into maintenance mode.
     *
     * Generates a unique cycle token for this downtime window so previous
     * bypass cookies cannot leak into new maintenance cycles.
     *
     * @param  array{message?: string, retry?: int, secret?: string}  $options
     */
    public static function down(array $options = []): void
    {
        $filePath = self::getFilePath();
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $cycleToken = bin2hex(random_bytes(16));
        $appName = self::getAppName();

        $data = [
            'time' => time(),
            'message' => $options['message'] ?? "We are currently performing scheduled maintenance. {$appName} will be back online shortly.",
            'retry' => max(5, (int) ($options['retry'] ?? 60)),
            'secret' => ! empty($options['secret']) ? trim((string) $options['secret']) : null,
            'template' => ! empty($options['template']) ? trim((string) $options['template']) : null,
            'token' => $cycleToken,
        ];

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Bring the application out of maintenance mode and clear bypass tokens.
     */
    public static function up(): bool
    {
        $filePath = self::getFilePath();
        $deleted = true;
        if (file_exists($filePath)) {
            $deleted = @unlink($filePath);
        }

        if (PHP_SAPI !== 'cli' && isset($_COOKIE[self::getCookieName()])) {
            self::clearBypassCookie();
        }

        return $deleted;
    }

    /**
     * Determine if maintenance mode is active.
     */
    public static function isDown(): bool
    {
        return file_exists(self::getFilePath());
    }

    /**
     * Alias for isDown().
     */
    public static function active(): bool
    {
        return self::isDown();
    }

    /**
     * Get maintenance payload data if down.
     *
     * @return array<string, mixed>|null
     */
    public static function getData(): ?array
    {
        $filePath = self::getFilePath();
        if (! file_exists($filePath)) {
            return null;
        }

        return json_decode((string) file_get_contents($filePath), true) ?: null;
    }

    /**
     * Set a Session Cookie for the bypass token.
     *
     * Uses 'expires => 0' so the cookie naturally vanishes when the browser closes,
     * and is strictly scoped to this maintenance cycle token.
     */
    public static function setBypassCookie(string $token): void
    {
        $isHttps = (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        setcookie(self::getCookieName(), $token, [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::getCookieName()] = $token;
    }

    /**
     * Expire and delete the bypass cookie immediately.
     */
    public static function clearBypassCookie(): void
    {
        $isHttps = (isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        setcookie(self::getCookieName(), '', [
            'expires' => time() - 86400,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[self::getCookieName()]);
    }

    /**
     * Resolve a safe redirect URL that preserves the user's current route
     * without dumping them onto an unauthenticated homepage.
     */
    private static function resolveSafeRedirect(?string $target): string
    {
        if (! empty($target)) {
            $parsed = parse_url($target);
            if (isset($parsed['path']) && ! isset($parsed['host'])) {
                $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

                return $parsed['path'].$query;
            }
            if (isset($parsed['host']) && isset($_SERVER['HTTP_HOST']) && strtolower($parsed['host']) === strtolower($_SERVER['HTTP_HOST'])) {
                $path = $parsed['path'] ?? '/';
                $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

                return $path.$query;
            }
        }

        return '/';
    }

    /**
     * Remove a single query parameter from a URI.
     */
    private static function removeQueryParam(string $uri, string $key): string
    {
        $parsed = parse_url($uri);
        $path = $parsed['path'] ?? '/';
        if (empty($parsed['query'])) {
            return $path;
        }

        parse_str($parsed['query'], $params);
        unset($params[$key]);

        $query = http_build_query($params);

        return $query !== '' ? "{$path}?{$query}" : $path;
    }

    private static function getFilePath(): string
    {
        return storage_path('down');
    }

    /**
     * Resolve a custom 503 error / maintenance view template path if available.
     */
    public static function resolveCustomView(?string $template = null): ?string
    {
        $candidates = [];

        if ($template !== null && $template !== '') {
            $candidates[] = $template;
            $candidates[] = base_path($template);
            $candidates[] = app_path($template);
            $candidates[] = app_path('views/error/'.$template);
            $candidates[] = app_path('views/error/'.$template.'.phtml');
            $candidates[] = app_path('views/scripts/'.$template);
            $candidates[] = app_path('views/scripts/'.$template.'.phtml');
        }

        $candidates[] = app_path('views/error/503.phtml');
        $candidates[] = app_path('views/scripts/error/503.phtml');
        $candidates[] = app_path('views/scripts/errors/503.phtml');
        $candidates[] = app_path('views/503.phtml');
        $candidates[] = base_path('public/503.html');

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Publish customizable 503 error view template to application/views/error/503.phtml.
     *
     * @param  string|null  $targetPath  Optional explicit destination path
     * @param  bool  $force  Overwrite existing view file
     * @return string Path of the published template
     */
    public static function publishView(?string $targetPath = null, bool $force = false): string
    {
        if ($targetPath !== null) {
            $target = $targetPath;
            $dir = dirname($target);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents($target, self::resolveTemplateStub());

            return $target;
        }

        if (class_exists(\ZF1\View\ErrorRenderer::class)) {
            $published = \ZF1\View\ErrorRenderer::publishViews(code: 503, force: $force);
            if (! empty($published)) {
                foreach ($published as $file) {
                    if (str_ends_with($file, '503.phtml')) {
                        return $file;
                    }
                }

                return $published[0];
            }
        }

        $target = function_exists('app_path') ? app_path('views/error/503.phtml') : dirname(__DIR__, 2).'/application/views/error/503.phtml';
        $dir = dirname($target);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($target, self::resolveTemplateStub());

        return $target;
    }

    /**
     * Resolve the 503 HTML stub template.
     */
    public static function resolveTemplateStub(): string
    {
        $stubFile = dirname(__DIR__).'/View/stubs/errors/503.phtml.stub';
        if (file_exists($stubFile)) {
            return (string) file_get_contents($stubFile);
        }

        return '';
    }

    /**
     * Render the built-in 503 maintenance page.
     */
    private static function renderDefaultHtml(string $message, int $retryAfter, bool $hasSecret = false, ?string $errorMessage = null): void
    {
        if (class_exists(\ZF1\View\ErrorRenderer::class)) {
            \ZF1\View\ErrorRenderer::renderHtml(503, null, [
                'message' => $message,
                'retryAfter' => $retryAfter,
                'hasSecret' => $hasSecret,
                'errorMessage' => $errorMessage,
            ]);

            return;
        }

        header('Content-Type: text/html; charset=utf-8');

        $code = 503;
        $title = 'Maintenance in Progress';
        $appName = self::getAppName();
        $homeUrl = function_exists('app_url') ? app_url('/') : '/';
        $currentUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
        $theme = 'auto';

        $stub = self::resolveTemplateStub();
        if ($stub !== '') {
            eval('?>'.$stub);

            return;
        }

        echo "<!DOCTYPE html><html><head><title>{$appName} - Maintenance</title></head><body><h1>{$appName} - Maintenance</h1><p>{$message}</p></body></html>";
    }
}
