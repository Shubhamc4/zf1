<?php

declare(strict_types=1);

namespace ZF1\Environment;

use Closure;
use Dotenv\Dotenv;
use Dotenv\Validator;
use RuntimeException;

/**
 * Universal Environment Manager built on vlucas/phpdotenv.
 */
final class Env
{
    private static bool $loaded = false;

    private static ?Dotenv $dotenv = null;

    /**
     * Load environment variables from .env file at the project root.
     *
     * @param  array<string>  $required  Optional list of environment variables to require as not empty
     */
    public static function load(string $projectRoot, string $file = '.env', array $required = []): ?Dotenv
    {
        $filePath = rtrim($projectRoot, '/\\').DIRECTORY_SEPARATOR.$file;

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            if (! empty($required)) {
                $missing = array_filter($required, fn ($v) => getenv($v) === false && ! isset($_ENV[$v]) && ! isset($_SERVER[$v]));
                if (! empty($missing)) {
                    throw new RuntimeException("Environment file [{$filePath}] not found and required variables are missing from environment: ".implode(', ', $missing));
                }
            }

            return null;
        }

        $dotenv = Dotenv::createImmutable($projectRoot, $file);
        $dotenv->safeLoad();

        self::$dotenv = $dotenv;
        self::$loaded = true;

        if (! empty($required)) {
            $dotenv->required($required)->notEmpty();
        }

        return $dotenv;
    }

    /**
     * Get the active Dotenv instance.
     */
    public static function getDotenv(): ?Dotenv
    {
        return self::$dotenv;
    }

    /**
     * Require one or more environment variables and return Dotenv Validator for chaining.
     *
     * Examples:
     *   Env::required(['APP_NAME', 'SESSION_NAME'])->notEmpty();
     *   Env::required('MAIL_PORT')->isInteger();
     *   Env::required('APP_ENV')->allowedValues(['local', 'production']);
     *
     * @param  array<string>|string  $variables
     */
    public static function required(array|string $variables): Validator
    {
        if (self::$dotenv === null) {
            throw new RuntimeException('Dotenv has not been loaded. Call Env::load() first.');
        }

        return self::$dotenv->required($variables);
    }

    /**
     * Validate one or more environment variables if present and return Dotenv Validator for chaining.
     *
     * Examples:
     *   Env::ifPresent('PORT')->isInteger();
     *   Env::ifPresent('DEBUG')->isBoolean();
     *
     * @param  array<string>|string  $variables
     */
    public static function ifPresent(array|string $variables): Validator
    {
        if (self::$dotenv === null) {
            throw new RuntimeException('Dotenv has not been loaded. Call Env::load() first.');
        }

        return self::$dotenv->ifPresent($variables);
    }

    /**
     * Configure or validate using the native Dotenv instance via callback.
     *
     * Example:
     *   Env::validate(function (Dotenv $dotenv) {
     *       $dotenv->required(['APP_NAME', 'SESSION_NAME'])->notEmpty();
     *       $dotenv->ifPresent('PORT')->isInteger();
     *   });
     *
     * @param  callable(Dotenv): void  $callback
     */
    public static function validate(callable $callback): void
    {
        if (self::$dotenv !== null) {
            $callback(self::$dotenv);
        }
    }

    /**
     * Check if an environment variable exists.
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Retrieve an environment variable value with optional default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default instanceof Closure ? $default() : $default;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'empty', '(empty)' => '',
                'null', '(null)' => null,
                default => preg_replace('/\A([\'"])(.*)\1\z/s', '$2', $value),
            };
        }

        return $value;
    }

    /**
     * Check if environment has been loaded.
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
