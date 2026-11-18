<?php

declare(strict_types=1);

namespace ZF1\View;

use Throwable;
use Zend_Controller_Action;
use Zend_Controller_Front;
use Zend_Controller_Plugin_ErrorHandler;
use Zend_Layout;
use Zend_Log;
use Zend_View;

/**
 * Modern Centralized Error and Exception Renderer for ZF1 Applications.
 */
final class ErrorRenderer
{
    /**
     * Active error theme ('auto', 'dark', 'light').
     */
    private static string $theme = 'auto';

    /**
     * Custom master error layout template path.
     */
    private static ?string $customLayout = null;

    /**
     * Base list of sensitive keys to sanitize in error outputs.
     *
     * @var array<string>
     */
    private static array $defaultSensitiveKeys = [
        'password', 'passwd', 'pwd', 'secret', 'token', 'auth', 'key',
        'api_key', 'private', 'cookie', 'session', 'hash', 'salt', 'cert',
        'signature', 'access_token', 'refresh_token', 'cvv', 'card', 'pin',
        'ssn', 'credit', 'app_key', 'db_password', 'secret_key',
    ];

    /**
     * Additional user-defined sensitive keys.
     *
     * @var array<string>
     */
    private static array $additionalSensitiveKeys = [];

    /**
     * Map of standard HTTP error codes to titles.
     *
     * @var array<int, string>
     */
    private static array $statusTitles = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Access Forbidden',
        404 => 'Page Not Found',
        419 => 'Page Expired',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    /**
     * Map of standard HTTP error codes to default descriptions.
     *
     * @var array<int, string>
     */
    private static array $statusMessages = [
        400 => 'The request could not be understood by the server due to malformed syntax.',
        401 => 'Authentication is required to access this resource. Please sign in and try again.',
        403 => 'You do not have permission to access the requested resource.',
        404 => 'The page or resource you are looking for could not be found or has been moved.',
        419 => 'Your session or CSRF security token has expired. Please refresh the page and try again.',
        422 => 'The given data was invalid and could not be processed.',
        429 => 'You have sent too many requests in a given amount of time. Please slow down and try again later.',
        500 => 'An unexpected error occurred on our server. Our technical team has been notified.',
        502 => 'The server received an invalid response from the upstream server.',
        503 => 'The server is temporarily unavailable or undergoing scheduled maintenance. Please check back shortly.',
        504 => 'The server did not receive a timely response from an upstream server.',
    ];

    /**
     * Get or set the error display theme ('auto', 'dark', 'light').
     */
    public static function theme(?string $theme = null): string
    {
        if ($theme !== null) {
            $normalized = strtolower(trim($theme));
            self::$theme = in_array($normalized, ['dark', 'light', 'auto'], true) ? $normalized : 'auto';
        }

        if (self::$theme === 'auto' && class_exists(\ZF1\Foundation\Kernel::class)) {
            $kernelClass = \ZF1\Foundation\Kernel::resolveKernelClass();
            if (method_exists($kernelClass, 'getErrorTheme') && $kernelClass::getErrorTheme() !== 'auto') {
                self::$theme = $kernelClass::getErrorTheme();
            }
        }

        return self::$theme;
    }

    /**
     * Get or set a custom master error layout template path.
     */
    public static function layout(?string $path = null): ?string
    {
        if ($path !== null) {
            self::$customLayout = $path;
        }

        return self::$customLayout;
    }

    /**
     * Add one or more custom sensitive keys to be masked.
     *
     * @param  array<string>|string  $keys
     */
    public static function addSensitiveKeys(array|string $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $k) {
            $cleanKey = strtolower(trim((string) $k));
            if ($cleanKey !== '' && ! in_array($cleanKey, self::$additionalSensitiveKeys, true)) {
                self::$additionalSensitiveKeys[] = $cleanKey;
            }
        }
    }

    /**
     * Set explicit list of additional sensitive keys.
     *
     * @param  array<string>  $keys
     */
    public static function setSensitiveKeys(array $keys): void
    {
        self::$additionalSensitiveKeys = [];
        self::addSensitiveKeys($keys);
    }

    /**
     * Get all registered sensitive keys.
     *
     * @return array<string>
     */
    public static function getSensitiveKeys(): array
    {
        $keys = array_merge(self::$defaultSensitiveKeys, self::$additionalSensitiveKeys);

        if (class_exists(\ZF1\Foundation\Kernel::class)) {
            $kernelClass = \ZF1\Foundation\Kernel::resolveKernelClass();
            if (method_exists($kernelClass, 'getSensitiveKeys')) {
                $kernelKeys = $kernelClass::getSensitiveKeys();
                if (! empty($kernelKeys)) {
                    $keys = array_merge($keys, array_map('strtolower', array_map('trim', (array) $kernelKeys)));
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Fluent bulk configuration.
     *
     * @param  array{theme?: string, layout?: string, sensitive_keys?: array<string>}  $options
     */
    public static function configure(array $options): void
    {
        if (isset($options['theme'])) {
            self::theme((string) $options['theme']);
        }
        if (isset($options['layout'])) {
            self::layout((string) $options['layout']);
        }
        if (isset($options['sensitive_keys']) && is_array($options['sensitive_keys'])) {
            self::addSensitiveKeys($options['sensitive_keys']);
        }
    }

    /**
     * Clean and normalize an exception message, stripping any appended stack traces or file info.
     */
    public static function cleanMessage(Throwable|string $source): string
    {
        if ($source instanceof Throwable) {
            $prev = $source->getPrevious();
            if ($prev !== null && str_contains($source->getMessage(), '#0 ')) {
                return self::cleanMessage($prev->getMessage());
            }
            $message = $source->getMessage();
        } else {
            $message = (string) $source;
        }

        if (preg_match('/^(.*?)(?:#0\s+.*|\n#\d+\s+.*)$/s', $message, $matches)) {
            $clean = trim($matches[1]);
            if ($clean !== '') {
                $message = $clean;
            }
        }

        if (preg_match('/^(.*?)(?:\s+in\s+[a-zA-Z0-9_\/\\\\.-]+\s+on\s+line\s+\d+|\s+in\s+[a-zA-Z0-9_\/\\\\.-]+:\d+)$/i', $message, $matches)) {
            $clean = trim($matches[1]);
            if ($clean !== '') {
                $message = $clean;
            }
        }

        $trimmed = trim($message);

        if (preg_match('/^Action\s+"([^"]+)"\s+does not exist/i', $trimmed, $matches)) {
            return "The requested route '{$matches[1]}' could not be found.";
        }

        if (preg_match('/^Invalid controller specified\s*(?:\(([^)]+)\))?/i', $trimmed, $matches)) {
            return ! empty($matches[1])
                ? "The requested route '{$matches[1]}' could not be found."
                : 'The requested route could not be found.';
        }

        if (preg_match('/^Method\s+"([^"]+)"\s+does not exist/i', $trimmed)) {
            return 'The requested route could not be found.';
        }

        if (preg_match('/^Controller\s+"([^"]+)"\s+is not an instance of/i', $trimmed)) {
            return 'The requested route could not be found.';
        }

        return $trimmed;
    }

    /**
     * Handle error rendering inside Zend MVC Controller dispatch loop (e.g. ErrorController::errorAction).
     *
     * @param  array<string, mixed>  $context
     */
    public static function handleMvc(
        Zend_Controller_Action $controller,
        ?int $code = null,
        ?Throwable $exception = null,
        array $context = []
    ): void {
        $errorHandler = $controller->getRequest()->getParam('error_handler');

        if ($errorHandler !== null) {
            if ($exception === null && isset($errorHandler->exception) && $errorHandler->exception instanceof Throwable) {
                $exception = $errorHandler->exception;
            }

            if ($code === null) {
                if (class_exists(\ZF1\Exceptions\HttpException::class) && $exception instanceof \ZF1\Exceptions\HttpException) {
                    $code = $exception->getStatusCode();
                } else {
                    $errorType = $errorHandler->type ?? null;
                    $code = match ($errorType) {
                        Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE,
                        Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER,
                        Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION => 404,
                        default => ($exception !== null && $exception->getCode() >= 400 && $exception->getCode() <= 599)
                            ? (int) $exception->getCode()
                            : 500,
                    };
                }
            }
        } elseif ($code === null) {
            $code = (class_exists(\ZF1\Exceptions\HttpException::class) && $exception instanceof \ZF1\Exceptions\HttpException)
                ? $exception->getStatusCode()
                : (($exception !== null && $exception->getCode() >= 400 && $exception->getCode() <= 599) ? (int) $exception->getCode() : 500);
        }

        if (method_exists($controller, 'disableLayout')) {
            $controller->disableLayout();
        }
        if (method_exists($controller, 'disableView')) {
            $controller->disableView();
        }

        $response = $controller->getResponse();
        if ($response !== null && method_exists($response, 'setHttpResponseCode')) {
            $response->setHttpResponseCode($code);
        }

        self::render($code, $exception, $context);
    }

    /**
     * Render an uncaught exception or fatal error from UnifiedErrorHandler.
     */
    public static function renderException(Throwable $e, int $statusCode = 500): void
    {
        $code = ($statusCode >= 400 && $statusCode <= 599) ? $statusCode : 500;
        self::render($code, $e);
        exit(1);
    }

    /**
     * Render the HTTP error response (JSON, Rich Debug HTML, or Production HTML).
     *
     * @param  array<string, mixed>  $context
     */
    public static function render(int $code, ?Throwable $exception = null, array $context = []): void
    {
        if (class_exists(\ZF1\Logging\UnifiedErrorHandler::class)) {
            if ($exception !== null) {
                if (\ZF1\Logging\UnifiedErrorHandler::shouldReport($exception, $code)) {
                    \ZF1\Logging\UnifiedErrorHandler::logException($exception, $code);
                }
            } elseif (\ZF1\Logging\UnifiedErrorHandler::shouldReport($code)) {
                $file = $context['file'] ?? ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
                $line = (int) ($context['line'] ?? 0);
                $message = $context['message'] ?? (self::$statusMessages[$code] ?? 'Server Error');

                $params = ! empty($_REQUEST) ? $_REQUEST : [];
                $params = self::sanitizeArray($params);

                $data = [
                    'Time' => \ZF1\Foundation\Bootstrap::now(),
                    'User ID' => currentUserId('Guest'),
                    'IP' => function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                    'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                    'Code' => $code,
                    'File' => $file,
                    'Line' => $line,
                    'Message' => "HTTP {$code}: {$message}",
                    'Stack' => '',
                    'Params' => $params,
                ];
                \ZF1\Logging\UnifiedErrorHandler::writeLog($data, Zend_Log::CRIT);
            }
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (! headers_sent()) {
            http_response_code($code);

            if ($exception instanceof \ZF1\Exceptions\HttpException) {
                foreach ($exception->getHeaders() as $name => $val) {
                    header("{$name}: {$val}");
                }
            }

            if ($code === 503 && class_exists(\ZF1\System\MaintenanceMode::class) && \ZF1\System\MaintenanceMode::isDown()) {
                $downData = \ZF1\System\MaintenanceMode::getData() ?: [];
                $retry = (int) ($downData['retry'] ?? 60);
                header("Retry-After: {$retry}");
            }
        }

        if (function_exists('request_wants_json') && request_wants_json()) {
            self::renderJson($code, $exception, $context);

            return;
        }

        if ($code >= 500 && $code !== 503 && self::isDebug() && $exception !== null && ! ($exception instanceof \ZF1\Exceptions\HttpException && $code !== 500)) {
            self::renderDebugPage($exception, $code, $context);

            return;
        }

        self::renderHtml($code, $exception, $context);
    }

    /**
     * Render standardized JSON response for API or AJAX requests.
     *
     * @param  array<string, mixed>  $context
     */
    public static function renderJson(int $code, ?Throwable $exception = null, array $context = []): void
    {
        if (! headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $isDebug = self::isDebug();
        $title = self::getDefaultTitle($code);
        $message = ($exception !== null && ($isDebug || $code < 500))
            ? self::cleanMessage($exception)
            : self::getDefaultMessage($code);

        $payload = [
            'status' => $code,
            'error' => $title,
            'message' => $message ?: $title,
        ];

        if ($code === 503 && class_exists(\ZF1\System\MaintenanceMode::class) && \ZF1\System\MaintenanceMode::isDown()) {
            $payload['maintenance'] = true;
            $downData = \ZF1\System\MaintenanceMode::getData() ?: [];
            $payload['retry_after'] = (int) ($downData['retry'] ?? 60);
            if (! empty($downData['message']) && ($message === self::getDefaultMessage(503) || empty($message))) {
                $payload['message'] = (string) $downData['message'];
            }
        }

        if ($isDebug && $exception !== null) {
            $origin = function_exists('resolveAppOrigin')
                ? resolveAppOrigin($exception)
                : ['file' => $exception->getFile(), 'line' => $exception->getLine()];

            $payload['exception'] = get_class($exception);
            $payload['file'] = $origin['file'];
            $payload['line'] = $origin['line'];
            $payload['trace'] = function_exists('formatCleanTrace')
                ? explode("\n", formatCleanTrace($exception))
                : explode("\n", $exception->getTraceAsString());
        }

        if (! empty($context)) {
            $payload['context'] = self::sanitizeArray($context);
        }

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Render HTML error page, resolving project custom views first and falling back to framework stubs.
     *
     * @param  array<string, mixed>  $context
     */
    public static function renderHtml(int $code, ?Throwable $exception = null, array $context = []): void
    {
        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $title = $context['title'] ?? self::getDefaultTitle($code);
        $rawMessage = $context['message'] ?? (
            ($exception !== null && $code < 500)
                ? $exception->getMessage()
                : self::getDefaultMessage($code)
        );

        if ($code === 503 && class_exists(\ZF1\System\MaintenanceMode::class) && \ZF1\System\MaintenanceMode::isDown()) {
            $downData = \ZF1\System\MaintenanceMode::getData() ?: [];
            if (! empty($downData['message']) && ($rawMessage === self::getDefaultMessage(503) || empty($rawMessage))) {
                $rawMessage = (string) $downData['message'];
            }
        }

        $message = self::cleanMessage($rawMessage);

        $appName = function_exists('app_name') ? app_name() : (defined('APP_NAME') ? (string) APP_NAME : 'ZF1');
        $homeUrl = function_exists('app_url') ? app_url('/') : '/';
        $isDebug = self::isDebug();
        $theme = self::theme();

        $downData = ($code === 503 && class_exists(\ZF1\System\MaintenanceMode::class) && \ZF1\System\MaintenanceMode::isDown())
            ? (\ZF1\System\MaintenanceMode::getData() ?: [])
            : [];
        $retryAfter = (int) ($downData['retry'] ?? 60);
        $secret = ! empty($downData['secret']) ? (string) $downData['secret'] : null;
        $hasSecret = $secret !== null;
        $errorMessage = $context['errorMessage'] ?? null;
        $data = $downData;

        $customView = self::resolveCustomView($code);
        if ($customView !== null) {
            $view = null;
            if (class_exists(Zend_Layout::class) && Zend_Layout::getMvcInstance()) {
                $view = Zend_Layout::getMvcInstance()->getView();
            }
            if ($view === null && class_exists(Zend_View::class)) {
                $view = new Zend_View;
            }
            if ($view === null) {
                $view = new class
                {
                    public function __call(string $name, array $args): string
                    {
                        return '';
                    }

                    public function __get(string $name): mixed
                    {
                        return null;
                    }
                };
            }

            $scope = (function () use ($customView, $code, $title, $message, $appName, $homeUrl, $isDebug, $theme, $data, $errorMessage, $retryAfter, $secret, $hasSecret) {
                $this->code = $code;
                $this->title = $title;
                $this->message = $message;
                $this->appName = $appName;
                $this->homeUrl = $homeUrl;
                $this->isDebug = $isDebug;
                $this->theme = $theme;
                $this->data = $data;
                $this->errorMessage = $errorMessage;
                $this->retryAfter = $retryAfter;
                $this->secret = $secret;
                $this->hasSecret = $hasSecret;

                require $customView;
            })->bindTo($view, $view);

            $scope();

            return;
        }

        $stubFile = __DIR__."/stubs/errors/{$code}.phtml.stub";
        if (file_exists($stubFile)) {
            require $stubFile;

            return;
        }

        $layoutFile = self::resolveLayoutPath();
        if (file_exists($layoutFile)) {
            require $layoutFile;

            return;
        }

        echo "<!DOCTYPE html><html><head><title>{$code} {$title}</title></head><body><h1>{$code} {$title}</h1><p>{$message}</p></body></html>";
    }

    /**
     * Resolve the underlying root exception, extracting from Zend MVC response or previous exceptions.
     */
    public static function resolveRootException(Throwable $e): Throwable
    {
        if (class_exists('Zend_Controller_Front', false)) {
            try {
                $front = Zend_Controller_Front::getInstance();
                $resp = $front ? $front->getResponse() : null;
                if ($resp !== null && $resp->isException()) {
                    $original = $resp->getException();
                    if (! empty($original[0]) && $original[0] !== $e) {
                        $e = $original[0];
                    }
                }
            } catch (Throwable) {
                // Ignore and proceed
            }
        }

        while ($e->getPrevious() !== null) {
            $class = get_class($e);
            if (str_contains($class, 'Zend_') || $class === 'Exception' || $class === 'ErrorException') {
                $e = $e->getPrevious();
            } else {
                break;
            }
        }

        return $e;
    }

    /**
     * Render the rich Laravel-style exception debug inspector page.
     *
     * @param  array<string, mixed>  $context
     */
    public static function renderDebugPage(Throwable $exception, int $code = 500, array $context = []): void
    {
        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $rootException = self::resolveRootException($exception);
        $exceptionClass = get_class($rootException);
        $wrappedExceptionClass = get_class($exception);
        $exceptionMessage = self::cleanMessage($rootException);

        $origin = function_exists('resolveAppOrigin')
            ? resolveAppOrigin($rootException)
            : ['file' => $rootException->getFile(), 'line' => $rootException->getLine()];

        $isProjectOrigin = function_exists('is_framework_path')
            ? ! is_framework_path((string) $origin['file'])
            : true;

        $cleanTrace = function_exists('formatCleanTrace')
            ? formatCleanTrace($rootException)
            : $rootException->getTraceAsString();

        $rawTrace = $rootException->getTraceAsString();
        $codeSnippet = self::getCodeSnippet((string) $origin['file'], (int) $origin['line']);

        $hintMessage = null;
        if (empty($codeSnippet) && str_contains($exceptionMessage, 'Invalid controller specified (error)')) {
            $hintMessage = 'Zend Framework MVC attempted to forward an uncaught exception to <code>ErrorController</code>, but <code>application/controllers/ErrorController.php</code> was not found.<br><br>👉 Run <strong><code>php zf foundation:install --errors</code></strong> to scaffold a standard ErrorController, or publish customizable error views with <strong><code>php zf error:publish</code></strong>.';
        }

        $appName = function_exists('app_name') ? app_name() : (defined('APP_NAME') ? (string) APP_NAME : 'ZF1');
        $theme = self::theme();

        $requestInfo = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').($_SERVER['REQUEST_URI'] ?? '/'),
            'path' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip' => function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ];

        $queryParams = ! empty($_GET) ? self::sanitizeArray($_GET) : [];
        $postParams = ! empty($_POST) ? self::sanitizeArray($_POST) : [];
        $headers = self::resolveHeaders();
        $session = ! empty($_SESSION) ? self::sanitizeArray($_SESSION) : [];
        $server = self::sanitizeArray($_SERVER);

        $debugStub = __DIR__.'/stubs/errors/debug.phtml.stub';
        if (file_exists($debugStub)) {
            require $debugStub;

            return;
        }

        self::renderHtml($code, $exception, $context);
    }

    /**
     * Resolve project custom view file path if available.
     *
     * Checks user's specified application/views/error/{code}.phtml as well as standard ZF1 view paths.
     */
    public static function resolveCustomView(int|string $code): ?string
    {
        if (! function_exists('app_path')) {
            return null;
        }

        $candidates = [
            app_path("views/error/{$code}.phtml"),
            app_path("views/scripts/error/{$code}.phtml"),
            app_path("views/scripts/errors/{$code}.phtml"),
            app_path("views/errors/{$code}.phtml"),
            app_path("views/{$code}.phtml"),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve active master error layout path.
     */
    public static function resolveLayoutPath(): string
    {
        if (self::$customLayout === null && class_exists(\ZF1\Foundation\Kernel::class)) {
            $kernelClass = \ZF1\Foundation\Kernel::resolveKernelClass();
            if (method_exists($kernelClass, 'getErrorLayout') && $kernelClass::getErrorLayout() !== null) {
                self::$customLayout = $kernelClass::getErrorLayout();
            }
        }

        if (self::$customLayout !== null) {
            $candidates = [
                self::$customLayout,
                function_exists('app_path') ? app_path(self::$customLayout) : null,
                function_exists('base_path') ? base_path(self::$customLayout) : null,
            ];
            foreach ($candidates as $candidate) {
                if ($candidate !== null && is_file($candidate) && is_readable($candidate)) {
                    return $candidate;
                }
            }
        }

        if (function_exists('app_path')) {
            $candidates = [
                app_path('views/error/layout.phtml'),
                app_path('views/scripts/error/layout.phtml'),
                app_path('views/scripts/errors/layout.phtml'),
                app_path('views/errors/layout.phtml'),
            ];
            foreach ($candidates as $candidate) {
                if (is_file($candidate) && is_readable($candidate)) {
                    return $candidate;
                }
            }
        }

        return __DIR__.'/stubs/errors/layout.phtml.stub';
    }

    /**
     * Publish customizable error view templates to the application views directory.
     *
     * @return array<string> Paths of published view files
     */
    public static function publishViews(?string $targetDir = null, ?int $code = null, bool $force = false): array
    {
        $destinationDir = $targetDir ?: (function_exists('app_path') ? app_path('views/error') : dirname(__DIR__, 3).'/application/views/error');
        if (! is_dir($destinationDir)) {
            @mkdir($destinationDir, 0775, true);
        }

        $stubsDir = __DIR__.'/stubs/errors';
        $published = [];

        $layoutDest = $destinationDir.'/layout.phtml';
        if ($force || ! file_exists($layoutDest)) {
            $layoutContent = (string) file_get_contents($stubsDir.'/layout.phtml.stub');
            file_put_contents($layoutDest, $layoutContent);
            $published[] = $layoutDest;
        }

        $codesToPublish = ($code !== null)
            ? [$code]
            : [400, 401, 403, 404, 419, 429, 500, 503];

        foreach ($codesToPublish as $statusCode) {
            $destFile = $destinationDir."/{$statusCode}.phtml";
            if (! $force && file_exists($destFile)) {
                continue;
            }

            $sourceStub = $stubsDir."/{$statusCode}.phtml.stub";
            if (file_exists($sourceStub)) {
                $content = (string) file_get_contents($sourceStub);
                file_put_contents($destFile, $content);
                $published[] = $destFile;
            }
        }

        return $published;
    }

    /**
     * Extract source code preview lines surrounding the given error line.
     *
     * @return array<array{line: int, code: string, is_target: bool}>
     */
    public static function getCodeSnippet(string $filePath, int $errorLine, int $contextLines = 8): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return [];
        }

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false || empty($lines)) {
            return [];
        }

        $totalLines = count($lines);
        $start = max(1, $errorLine - $contextLines);
        $end = min($totalLines, $errorLine + $contextLines);

        $snippet = [];
        for ($i = $start; $i <= $end; $i++) {
            $snippet[] = [
                'line' => $i,
                'code' => $lines[$i - 1] ?? '',
                'is_target' => ($i === $errorLine),
            ];
        }

        return $snippet;
    }

    /**
     * Mask sensitive values (passwords, tokens, secret keys) in array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeArray(array $data): array
    {
        $sensitiveKeys = self::getSensitiveKeys();

        $sanitized = [];
        foreach ($data as $key => $value) {
            $strKey = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $needle) {
                if (str_contains($strKey, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '******';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Retrieve and sanitize HTTP headers from current request.
     *
     * @return array<string, string>
     */
    private static function resolveHeaders(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $all = getallheaders();
            if (is_array($all)) {
                foreach ($all as $k => $v) {
                    $headers[(string) $k] = (string) $v;
                }
            }
        }

        if (empty($headers)) {
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                    $headers[$name] = (string) $v;
                }
            }
        }

        return self::sanitizeArray($headers);
    }

    /**
     * Get default title for given HTTP status code.
     */
    public static function getDefaultTitle(int $code): string
    {
        return self::$statusTitles[$code] ?? 'Error';
    }

    /**
     * Get default description message for given HTTP status code.
     */
    public static function getDefaultMessage(int $code): string
    {
        return self::$statusMessages[$code] ?? 'An unexpected error has occurred.';
    }

    /**
     * Determine if debug mode is enabled.
     */
    public static function isDebug(): bool
    {
        return (bool) env('APP_DEBUG', false);
    }
}
