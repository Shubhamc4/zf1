<?php

declare(strict_types=1);

namespace ZF1\Logging;

use Exception;
use Throwable;
use Zend_Log;

final class UnifiedErrorHandler
{
    private static bool $registered = false;

    /**
     * Register global PHP error, uncaught exception, and fatal shutdown handlers.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        $maxPriority = self::resolveMaxPriority();

        // 1. Convert PHP warnings and errors into log entries
        set_error_handler(static function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null) use ($maxPriority) {
            if (! (error_reporting() & $errno)) {
                return false;
            }

            $priority = match ($errno) {
                E_USER_ERROR, E_RECOVERABLE_ERROR => Zend_Log::ERR,
                E_WARNING, E_USER_WARNING => Zend_Log::WARN,
                E_NOTICE, E_USER_NOTICE => Zend_Log::NOTICE,
                default => Zend_Log::INFO,
            };

            // Discard logs below configured priority threshold
            if ($priority > $maxPriority) {
                return false;
            }

            $origin = resolveAppOrigin(['file' => $errfile, 'line' => $errline]);

            $data = [
                'Time' => \ZF1\Foundation\Bootstrap::now(),
                'User ID' => self::resolveUserId(),
                'IP' => getIpAddress(),
                'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'Code' => $errno,
                'File' => $origin['file'],
                'Line' => $origin['line'],
                'Message' => "PHP Error: {$errstr}",
                'Stack' => formatCleanTrace((new Exception)->getTraceAsString()),
                'Params' => ! empty($_REQUEST) ? $_REQUEST : [],
            ];

            self::writeLog($data, $priority);

            return false;
        });

        // 2. Uncaught Exception Handler
        set_exception_handler(static function (Throwable $e) {
            $origin = resolveAppOrigin($e);

            $data = [
                'Time' => \ZF1\Foundation\Bootstrap::now(),
                'User ID' => self::resolveUserId(),
                'IP' => getIpAddress(),
                'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'Code' => $e->getCode() ?: 500,
                'File' => $origin['file'],
                'Line' => $origin['line'],
                'Message' => 'Uncaught '.get_class($e).': '.$e->getMessage(),
                'Stack' => formatCleanTrace($e),
                'Params' => ! empty($_REQUEST) ? $_REQUEST : [],
            ];

            self::writeLog($data, Zend_Log::CRIT);

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "\n\033[31m[ERROR] ".get_class($e).': '.$e->getMessage()."\033[0m\n".$e->getTraceAsString()."\n");
            }
        });

        // 3. Fatal Shutdown Handler
        register_shutdown_function(static function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $origin = resolveAppOrigin(['file' => $error['file'], 'line' => $error['line']]);

                $data = [
                    'Time' => \ZF1\Foundation\Bootstrap::now(),
                    'User ID' => self::resolveUserId(),
                    'IP' => getIpAddress(),
                    'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                    'Code' => $error['type'],
                    'File' => $origin['file'],
                    'Line' => $origin['line'],
                    'Message' => 'Fatal PHP Error: '.$error['message'],
                    'Stack' => '',
                    'Params' => ! empty($_REQUEST) ? $_REQUEST : [],
                ];

                self::writeLog($data, Zend_Log::EMERG);

                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, "\n\033[31m[FATAL] ".$error['message'].' in '.$error['file'].':'.$error['line']."\033[0m\n");
                }
            }
        });
    }

    /**
     * Resolve current user ID safely without risking undeclared property errors.
     */
    private static function resolveUserId(): string
    {
        foreach (['\App\Bootstrap', '\Bootstrap', 'Bootstrap'] as $class) {
            if (class_exists($class, false) && property_exists($class, 'loginSession')) {
                $session = $class::$loginSession;
                if ($session && isset($session->id)) {
                    return (string) $session->id;
                }
            }
        }

        return 'Guest';
    }

    /**
     * Resolve the maximum log priority threshold using APP_LOG_LEVEL, LOG_LEVEL, APP_DEBUG, and APP_ENV.
     */
    public static function resolveMaxPriority(): int
    {
        $explicitLevel = env('APP_LOG_LEVEL', env('LOG_LEVEL'));

        if ($explicitLevel !== null && $explicitLevel !== '') {
            $level = strtolower((string) $explicitLevel);
        } else {
            $isDebug = (bool) env('APP_DEBUG', false);
            $appEnv = (string) env('APP_ENV', defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production');

            if ($isDebug) {
                $level = 'debug';
            } else {
                $level = ($appEnv === 'production') ? 'error' : 'warning';
            }
        }

        return match ($level) {
            'emergency', 'emerg' => Zend_Log::EMERG,  // 0
            'alert' => Zend_Log::ALERT,  // 1
            'critical', 'crit' => Zend_Log::CRIT,   // 2
            'error', 'err' => Zend_Log::ERR,    // 3
            'warning', 'warn' => Zend_Log::WARN,   // 4
            'notice' => Zend_Log::NOTICE, // 5
            'info' => Zend_Log::INFO,   // 6
            'debug' => Zend_Log::DEBUG,  // 7
            default => Zend_Log::WARN,
        };
    }

    /**
     * Write formatted log to info.log and dispatch to Discord.
     *
     * @param  array<string, mixed>  $data
     */
    public static function writeLog(array $data, int $priority = Zend_Log::ERR): void
    {
        \ZF1\Foundation\Bootstrap::log($data, $priority);
    }
}
