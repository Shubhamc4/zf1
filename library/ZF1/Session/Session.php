<?php

declare(strict_types=1);

namespace ZF1\Session;

use Closure;
use Throwable;
use Zend_Session;

/**
 * Modern Session Manager and Static Facade for Zend Framework 1.
 * Seamlessly integrates with Zend_Session, Zend_Session_Namespace, and native $_SESSION.
 */
final class Session
{
    /**
     * Whether flash data has been aged for this request cycle.
     */
    private static bool $flashAged = false;

    /**
     * Ensure session is started and configured.
     */
    public static function start(): void
    {
        if (self::isStarted()) {
            self::ageFlashData();

            return;
        }

        if (headers_sent()) {
            if (! isset($_SESSION) || ! is_array($_SESSION)) {
                $_SESSION = [];
            }
            self::ageFlashData();

            return;
        }

        if (class_exists('Zend_Session', false) && ! Zend_Session::isStarted()) {
            try {
                Zend_Session::start();
            } catch (Throwable) {
                // Ignore
            }
        } elseif (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (! isset($_SESSION) || ! is_array($_SESSION)) {
            $_SESSION = [];
        }

        self::ageFlashData();
    }

    /**
     * Check if a session has been started.
     */
    public static function isStarted(): bool
    {
        if (class_exists('Zend_Session', false)) {
            return Zend_Session::isStarted();
        }

        return session_status() === PHP_SESSION_ACTIVE || (isset($_SESSION) && is_array($_SESSION));
    }

    /**
     * Get the current session ID.
     */
    public static function id(): string
    {
        self::start();

        return session_id() ?: '';
    }

    /**
     * Set the current session ID.
     */
    public static function setId(string $id): void
    {
        if (! self::isStarted()) {
            session_id($id);
        } else {
            Zend_Session::setId($id);
        }
    }

    /**
     * Get the current session name.
     */
    public static function getName(): string
    {
        return session_name() ?: (string) env('SESSION_NAME', 'zf1');
    }

    /**
     * Retrieve an item from the session using dot notation.
     *
     * @param  mixed  $default  Value or Closure returning default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        if (empty($_SESSION)) {
            return $default instanceof Closure ? $default() : $default;
        }

        return function_exists('data_get')
            ? data_get($_SESSION, $key, $default)
            : ($_SESSION[$key] ?? ($default instanceof Closure ? $default() : $default));
    }

    /**
     * Store an item in the session using dot notation.
     *
     * @param  string|array<string, mixed>  $key
     */
    public static function put(string|array $key, mixed $value = null): void
    {
        self::start();

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::put((string) $k, $v);
            }

            return;
        }

        if (function_exists('data_set')) {
            data_set($_SESSION, $key, $value);
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Alias for put().
     *
     * @param  string|array<string, mixed>  $key
     */
    public static function set(string|array $key, mixed $value = null): void
    {
        self::put($key, $value);
    }

    /**
     * Determine if a key exists in the session and is not null.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Determine if a key exists in the session even if its value is null.
     */
    public static function exists(string $key): bool
    {
        self::start();

        if (empty($_SESSION)) {
            return false;
        }

        return function_exists('array_has') ? array_has($_SESSION, $key) : array_key_exists($key, $_SESSION);
    }

    /**
     * Determine if a key is missing from the session.
     */
    public static function missing(string $key): bool
    {
        return ! self::has($key);
    }

    /**
     * Retrieve all items in the session (excluding internal framework metadata).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::start();

        $data = $_SESSION ?? [];
        unset($data['_flash']);

        return $data;
    }

    /**
     * Get a subset of items from the session.
     *
     * @param  array<string>|string  $keys
     * @return array<string, mixed>
     */
    public static function only(array|string $keys): array
    {
        self::start();
        $keys = is_array($keys) ? $keys : [$keys];
        $all = self::all();

        return function_exists('array_only') ? array_only($all, $keys) : array_intersect_key($all, array_flip($keys));
    }

    /**
     * Get all items from the session except for a specified array of keys.
     *
     * @param  array<string>|string  $keys
     * @return array<string, mixed>
     */
    public static function except(array|string $keys): array
    {
        self::start();
        $keys = is_array($keys) ? $keys : [$keys];
        $all = self::all();

        return function_exists('array_except') ? array_except($all, $keys) : array_diff_key($all, array_flip($keys));
    }

    /**
     * Retrieve and delete an item from the session.
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        self::start();

        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    /**
     * Remove one or many items from the session using dot notation.
     *
     * @param  string|array<string>  $keys
     */
    public static function forget(string|array $keys): void
    {
        self::start();

        if (empty($_SESSION)) {
            return;
        }

        $keys = is_array($keys) ? $keys : [$keys];

        if (function_exists('array_forget')) {
            array_forget($_SESSION, $keys);
        } else {
            foreach ($keys as $k) {
                unset($_SESSION[$k]);
            }
        }
    }

    /**
     * Alias for forget().
     */
    public static function remove(string|array $keys): void
    {
        self::forget($keys);
    }

    /**
     * Push a value onto a session array.
     */
    public static function push(string $key, mixed $value): void
    {
        $array = self::get($key, []);
        if (! is_array($array)) {
            $array = [$array];
        }
        $array[] = $value;
        self::put($key, $array);
    }

    /**
     * Increment an integer session value.
     */
    public static function increment(string $key, int $amount = 1): int
    {
        $value = (int) self::get($key, 0) + $amount;
        self::put($key, $value);

        return $value;
    }

    /**
     * Decrement an integer session value.
     */
    public static function decrement(string $key, int $amount = 1): int
    {
        return self::increment($key, -$amount);
    }

    /**
     * Flash a key/value pair to the session for the next request only.
     */
    public static function flash(string $key, mixed $value = true): void
    {
        self::start();
        self::put($key, $value);

        if (! isset($_SESSION['_flash']['new'])) {
            $_SESSION['_flash']['new'] = [];
        }

        if (! in_array($key, $_SESSION['_flash']['new'], true)) {
            $_SESSION['_flash']['new'][] = $key;
        }

        // Remove from old if present
        if (isset($_SESSION['_flash']['old'])) {
            $_SESSION['_flash']['old'] = array_values(array_diff($_SESSION['_flash']['old'], [$key]));
        }
    }

    /**
     * Flash a key/value pair to the session for the immediate request only.
     */
    public static function now(string $key, mixed $value = true): void
    {
        self::start();
        self::put($key, $value);

        if (! isset($_SESSION['_flash']['old'])) {
            $_SESSION['_flash']['old'] = [];
        }

        if (! in_array($key, $_SESSION['_flash']['old'], true)) {
            $_SESSION['_flash']['old'][] = $key;
        }
    }

    /**
     * Reflash all flash data for one additional request.
     */
    public static function reflash(): void
    {
        self::start();

        $old = $_SESSION['_flash']['old'] ?? [];
        $new = $_SESSION['_flash']['new'] ?? [];

        $_SESSION['_flash']['new'] = array_values(array_unique(array_merge($new, $old)));
        $_SESSION['_flash']['old'] = [];
    }

    /**
     * Keep specific flash items for one additional request.
     *
     * @param  array<string>|string  $keys
     */
    public static function keep(array|string $keys): void
    {
        self::start();
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (isset($_SESSION['_flash']['old']) && in_array($key, $_SESSION['_flash']['old'], true)) {
                $_SESSION['_flash']['old'] = array_values(array_diff($_SESSION['_flash']['old'], [$key]));
                $_SESSION['_flash']['new'][] = $key;
            }
        }
    }

    /**
     * Get or generate the CSRF token from the session.
     */
    public static function token(): string
    {
        self::start();

        if (empty($_SESSION['_token'])) {
            self::regenerateToken();
        }

        return (string) $_SESSION['_token'];
    }

    /**
     * Regenerate the CSRF token value.
     */
    public static function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(20));
        self::put('_token', $token);

        return $token;
    }

    /**
     * Regenerate the session ID and optionally destroy the old session.
     */
    public static function regenerate(bool $destroyOldSession = false): bool
    {
        self::start();

        if (class_exists('Zend_Session', false)) {
            try {
                Zend_Session::regenerateId();

                return true;
            } catch (Throwable) {
                // Fallback to native
            }
        }

        if (function_exists('session_regenerate_id') && self::isStarted()) {
            if (! headers_sent()) {
                return @session_regenerate_id($destroyOldSession);
            }

            return true;
        }

        return false;
    }

    /**
     * Clear all session data, destroy the session, and regenerate session ID.
     */
    public static function invalidate(): bool
    {
        self::start();

        $_SESSION = [];

        if (class_exists('Zend_Session', false)) {
            try {
                Zend_Session::destroy(true);

                return true;
            } catch (Throwable) {
                // Fallback
            }
        }

        if (self::isStarted()) {
            session_destroy();

            return self::regenerate(true);
        }

        return true;
    }

    /**
     * Clear all session attributes.
     */
    public static function flush(): void
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * Alias for flush().
     */
    public static function clear(): void
    {
        self::flush();
    }

    /**
     * Age flash data: remove old flash items, move new flash items to old.
     */
    private static function ageFlashData(): void
    {
        if (self::$flashAged || empty($_SESSION)) {
            return;
        }

        self::$flashAged = true;

        if (isset($_SESSION['_flash']['old'])) {
            foreach ($_SESSION['_flash']['old'] as $oldKey) {
                self::forget($oldKey);
            }
        }

        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash']['new'] = [];
    }
}
