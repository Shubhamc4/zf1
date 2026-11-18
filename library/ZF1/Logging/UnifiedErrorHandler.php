<?php

declare(strict_types=1);

namespace ZF1\Logging;

use ErrorException;
use Exception;
use Throwable;
use Zend_Log;

use const STDERR;

final class UnifiedErrorHandler
{
    private static bool $registered = false;

    /**
     * Cache of already logged exception object IDs to prevent duplicate log entries.
     *
     * @var array<int, bool>
     */
    private static array $loggedExceptions = [];

    /**
     * Default list of exception classes that should never be logged to zf1.log.
     *
     * @var array<class-string<Throwable>>
     */
    private static array $dontReport = [
        \ZF1\Validation\ValidationException::class,
        \ZF1\Exceptions\NotFoundHttpException::class,
        \ZF1\Exceptions\AccessDeniedHttpException::class,
        \ZF1\Exceptions\BadRequestHttpException::class,
        \ZF1\Exceptions\UnauthorizedHttpException::class,
        \ZF1\Exceptions\MethodNotAllowedHttpException::class,
        \ZF1\Exceptions\TooManyRequestsHttpException::class,
        \ZF1\Exceptions\ServiceUnavailableHttpException::class,
    ];

    /**
     * Additional user-defined ignored exception classes.
     *
     * @var array<class-string<Throwable>>
     */
    private static array $additionalDontReport = [];

    /**
     * Register additional exception classes that should not be reported to zf1.log.
     *
     * @param  array<class-string<Throwable>>|class-string<Throwable>  $classes
     */
    public static function dontReport(array|string $classes): void
    {
        $classes = is_array($classes) ? $classes : [$classes];
        foreach ($classes as $class) {
            if (! in_array($class, self::$additionalDontReport, true)) {
                self::$additionalDontReport[] = $class;
            }
        }
    }

    /**
     * Determine if an exception or HTTP status code should be reported.
     */
    public static function shouldReport(Throwable|int $source, ?int $statusCode = null): bool
    {
        $code = 500;
        $exception = null;

        if (is_int($source)) {
            $code = $source;
        } elseif ($source instanceof Throwable) {
            $exception = $source;
            $code = $statusCode ?? (
                (class_exists(\ZF1\Exceptions\HttpException::class) && $source instanceof \ZF1\Exceptions\HttpException)
                    ? $source->getStatusCode()
                    : (($source->getCode() >= 400 && $source->getCode() <= 599) ? (int) $source->getCode() : 500)
            );
        }

        if ($code >= 400 && $code < 500) {
            return false;
        }

        if ($code === 503) {
            return false;
        }

        if ($exception !== null) {
            $rootEx = class_exists(\ZF1\View\ErrorRenderer::class)
                ? \ZF1\View\ErrorRenderer::resolveRootException($exception)
                : $exception;

            $ignored = array_merge(self::$dontReport, self::$additionalDontReport);

            if (class_exists(\ZF1\Foundation\Kernel::class)) {
                $kernelClass = \ZF1\Foundation\Kernel::resolveKernelClass();
                if (method_exists($kernelClass, 'getDontReport')) {
                    $ignored = array_merge($ignored, (array) $kernelClass::getDontReport());
                }
            }

            foreach ($ignored as $type) {
                if ($exception instanceof $type || $rootEx instanceof $type) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Register global PHP error, uncaught exception, and fatal shutdown handlers.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        set_error_handler(static function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null) {
            if (! (error_reporting() & $errno)) {
                return false;
            }

            if (! in_array($errno, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                return false;
            }

            $origin = resolveAppOrigin(['file' => $errfile, 'line' => $errline]);

            $params = ! empty($_REQUEST) ? $_REQUEST : [];
            if (class_exists(\ZF1\View\ErrorRenderer::class)) {
                $params = \ZF1\View\ErrorRenderer::sanitizeArray($params);
            }

            $data = [
                'Time' => \ZF1\Foundation\Bootstrap::now(),
                'User ID' => currentUserId('Guest'),
                'IP' => function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'Code' => $errno,
                'File' => $origin['file'],
                'Line' => $origin['line'],
                'Message' => "PHP Error: {$errstr}",
                'Stack' => formatCleanTrace((new Exception)->getTraceAsString()),
                'Params' => $params,
            ];

            self::writeLog($data, Zend_Log::ERR);

            return false;
        });

        set_exception_handler(static function (Throwable $e) {
            $rootEx = class_exists(\ZF1\View\ErrorRenderer::class)
                ? \ZF1\View\ErrorRenderer::resolveRootException($e)
                : $e;

            $statusCode = (class_exists(\ZF1\Exceptions\HttpException::class) && $rootEx instanceof \ZF1\Exceptions\HttpException)
                ? $rootEx->getStatusCode()
                : (($rootEx->getCode() >= 400 && $rootEx->getCode() <= 599) ? (int) $rootEx->getCode() : 500);

            if (self::shouldReport($rootEx, $statusCode)) {
                self::logException($rootEx, $statusCode);
            }

            $cleanMessage = class_exists(\ZF1\View\ErrorRenderer::class)
                ? \ZF1\View\ErrorRenderer::cleanMessage($rootEx)
                : $rootEx->getMessage();

            if (PHP_SAPI === 'cli' && defined('STDERR')) {
                fwrite(STDERR, "\n\033[31m[ERROR] ".get_class($rootEx).': '.$cleanMessage."\033[0m\n".$rootEx->getTraceAsString()."\n");
            } elseif (PHP_SAPI !== 'cli' && class_exists(\ZF1\View\ErrorRenderer::class)) {
                \ZF1\View\ErrorRenderer::renderException($rootEx, $statusCode);
            }
        });

        register_shutdown_function(static function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $origin = resolveAppOrigin(['file' => $error['file'], 'line' => $error['line']]);

                $params = ! empty($_REQUEST) ? $_REQUEST : [];
                if (class_exists(\ZF1\View\ErrorRenderer::class)) {
                    $params = \ZF1\View\ErrorRenderer::sanitizeArray($params);
                }

                $data = [
                    'Time' => \ZF1\Foundation\Bootstrap::now(),
                    'User ID' => currentUserId('Guest'),
                    'IP' => function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                    'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                    'Code' => $error['type'],
                    'File' => $origin['file'],
                    'Line' => $origin['line'],
                    'Message' => 'Fatal PHP Error: '.$error['message'],
                    'Stack' => '',
                    'Params' => $params,
                ];

                self::writeLog($data, Zend_Log::EMERG);

                if (PHP_SAPI === 'cli' && defined('STDERR')) {
                    fwrite(STDERR, "\n\033[31m[FATAL] ".$error['message'].' in '.$error['file'].':'.$error['line']."\033[0m\n");
                } elseif (PHP_SAPI !== 'cli' && class_exists(\ZF1\View\ErrorRenderer::class)) {
                    $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                    \ZF1\View\ErrorRenderer::renderException($e, 500);
                }
            }
        });
    }

    /**
     * Log an exception to zf1.log (and Discord) with resolved application origin, clean message, and clean stack trace.
     * Deduplicates to prevent duplicate log entries for the same exception object.
     */
    public static function logException(Throwable $e, ?int $code = null, ?int $priority = null): void
    {
        $rootEx = class_exists(\ZF1\View\ErrorRenderer::class)
            ? \ZF1\View\ErrorRenderer::resolveRootException($e)
            : $e;

        $statusCode = $code ?? (
            (class_exists(\ZF1\Exceptions\HttpException::class) && $rootEx instanceof \ZF1\Exceptions\HttpException)
                ? $rootEx->getStatusCode()
                : (($rootEx->getCode() >= 400 && $rootEx->getCode() <= 599) ? (int) $rootEx->getCode() : 500)
        );

        if (! self::shouldReport($rootEx, $statusCode)) {
            return;
        }

        $id = spl_object_id($e);
        if (isset(self::$loggedExceptions[$id])) {
            return;
        }
        self::$loggedExceptions[$id] = true;

        $rootId = spl_object_id($rootEx);
        self::$loggedExceptions[$rootId] = true;

        $origin = function_exists('resolveAppOrigin')
            ? resolveAppOrigin($rootEx)
            : ['file' => $rootEx->getFile(), 'line' => $rootEx->getLine()];

        $cleanMessage = class_exists(\ZF1\View\ErrorRenderer::class)
            ? \ZF1\View\ErrorRenderer::cleanMessage($rootEx)
            : $rootEx->getMessage();

        $logPriority = $priority ?? ($statusCode >= 500 ? Zend_Log::CRIT : Zend_Log::NOTICE);

        $maxPriority = self::resolveMaxPriority();
        if ($logPriority > $maxPriority) {
            return;
        }

        $params = ! empty($_REQUEST) ? $_REQUEST : [];
        if (class_exists(\ZF1\View\ErrorRenderer::class)) {
            $params = \ZF1\View\ErrorRenderer::sanitizeArray($params);
        }

        $data = [
            'Time' => \ZF1\Foundation\Bootstrap::now(),
            'User ID' => currentUserId('Guest'),
            'IP' => function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'Code' => $statusCode,
            'File' => $origin['file'],
            'Line' => $origin['line'],
            'Message' => 'Uncaught '.get_class($rootEx).': '.$cleanMessage,
            'Stack' => function_exists('formatCleanTrace') ? formatCleanTrace($rootEx) : $rootEx->getTraceAsString(),
            'Params' => $params,
        ];

        self::writeLog($data, $logPriority);
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
     * Write formatted log to zf1.log and dispatch to Discord.
     *
     * @param  array<string, mixed>  $data
     */
    public static function writeLog(array $data, int $priority = Zend_Log::ERR): void
    {
        \ZF1\Foundation\Bootstrap::log($data, $priority);
    }
}
