<?php

declare(strict_types=1);

namespace ZF1\Foundation;

use Stringable;
use Throwable;
use Zend_Controller_Front;
use Zend_Controller_Response_Http;
use ZF1\Session\Session;

/**
 * Fluent HTTP Redirect Response with session flashing and input retention.
 */
final class RedirectResponse implements Stringable
{
    /**
     * Session key used to remember the intended URL.
     */
    public const INTENDED_URL_KEY = 'url.intended';

    /**
     * The target redirect URL.
     */
    protected ?string $url = null;

    /**
     * HTTP status code (e.g. 302, 301, 303, 307).
     */
    protected int $status = 302;

    /**
     * Associated controller instance.
     */
    protected ?Controller $controller = null;

    /**
     * Additional HTTP response headers.
     *
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * Flag indicating if the redirect has already been sent.
     */
    protected bool $sent = false;

    /**
     * Create a new redirect response instance.
     */
    public function __construct(?string $url = null, int $status = 302, ?Controller $controller = null)
    {
        $this->url = $url;
        $this->status = $status;
        $this->controller = $controller;
    }

    /**
     * Set the target URL to redirect to.
     */
    public function to(string $url, int $status = 302): static
    {
        $this->url = $url;
        $this->status = $status;

        return $this;
    }

    /**
     * Redirect to the previous URL (HTTP_REFERER) or a given fallback.
     */
    public function back(?string $fallback = '/', int $status = 302): static
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $target = ($referer !== null && trim($referer) !== '') ? $referer : ($fallback ?? '/');

        return $this->to($target, $status);
    }

    /**
     * Redirect to the URL the user intended to access before authentication, or a fallback.
     */
    public function intended(?string $default = '/', int $status = 302): static
    {
        $path = Session::pull(self::INTENDED_URL_KEY, $default);
        $target = (string) ($path ?: $default ?: '/');

        if ($this->isExternalUrl($target)) {
            $target = (string) ($default ?: '/');
        }

        return $this->to($target, $status);
    }

    /**
     * Determine if a given URL points to an external or untrusted destination.
     */
    public function isExternalUrl(string $url): bool
    {
        $trimmed = trim($url);

        if (preg_match('/^(?:javascript|data):/i', $trimmed) === 1) {
            return true;
        }

        if (str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '//')) {
            return false;
        }

        if (str_starts_with($trimmed, '?') || str_starts_with($trimmed, '#')) {
            return false;
        }

        $host = parse_url($trimmed, PHP_URL_HOST);
        if ($host === null || $host === false || $host === '') {
            return false;
        }

        $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $currentHost = explode(':', (string) $currentHost)[0];
        $host = explode(':', (string) $host)[0];

        return strtolower($host) !== strtolower($currentHost);
    }

    /**
     * Remember the current URL in session and redirect to the given guest location.
     */
    public function guest(string $location, int $status = 302): static
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['GET', 'HEAD'], true)) {
            $this->setIntendedUrl($this->getCurrentUrl());
        }

        return $this->to($location, $status);
    }

    /**
     * Determine if an intended URL is saved in session.
     */
    public static function hasIntendedUrl(): bool
    {
        $url = Session::get(self::INTENDED_URL_KEY);

        return $url !== null && trim((string) $url) !== '';
    }

    /**
     * Set the intended URL in session.
     */
    public function setIntendedUrl(string $url): static
    {
        Session::put(self::INTENDED_URL_KEY, $url);

        return $this;
    }

    /**
     * Get the intended URL from session without clearing it.
     */
    public static function getIntendedUrl(?string $default = null): ?string
    {
        return Session::get(self::INTENDED_URL_KEY, $default);
    }

    /**
     * Clear the intended URL from the session.
     */
    public static function forgetIntendedUrl(): void
    {
        Session::forget(self::INTENDED_URL_KEY);
    }

    /**
     * Clear the intended URL from the session on this redirect instance.
     */
    public function clearIntendedUrl(): static
    {
        self::forgetIntendedUrl();

        return $this;
    }

    /**
     * Get the current request URI.
     */
    protected function getCurrentUrl(): string
    {
        if ($this->controller !== null) {
            $req = $this->controller->getRequest();
            if ($req && method_exists($req, 'getRequestUri')) {
                return $req->getRequestUri();
            }
        }

        if (class_exists('Zend_Controller_Front', false)) {
            $frontReq = Zend_Controller_Front::getInstance()->getRequest();
            if ($frontReq && method_exists($frontReq, 'getRequestUri')) {
                return $frontReq->getRequestUri();
            }
        }

        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Refresh the current page.
     */
    public function refresh(int $status = 302): static
    {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';

        return $this->to($currentUri, $status);
    }

    /**
     * Redirect to an external URL.
     */
    public function away(string $url, int $status = 302): static
    {
        return $this->to($url, $status);
    }

    /**
     * Redirect to a named route.
     *
     * @param  array<string, mixed>  $params
     */
    public function route(string $name, array $params = [], int $status = 302, bool $reset = true): static
    {
        try {
            $router = Zend_Controller_Front::getInstance()->getRouter();
            $url = $router->assemble($params, $name, $reset);
        } catch (Throwable) {
            $url = '/';
        }

        return $this->to($url, $status);
    }

    /**
     * Redirect to a controller action.
     *
     * @param  array<string, mixed>  $params
     */
    public function action(
        string $action,
        ?string $controller = null,
        ?string $module = null,
        array $params = [],
        int $status = 302
    ): static {
        if ($controller === null && $this->controller !== null) {
            $req = $this->controller->getRequest();
            $controller = $req ? $req->getControllerName() : null;
            if ($module === null && $req) {
                $module = $req->getModuleName();
            }
        }

        $params['action'] = $action;
        if ($controller !== null) {
            $params['controller'] = $controller;
        }
        if ($module !== null) {
            $params['module'] = $module;
        }

        return $this->route('default', $params, $status, false);
    }

    /**
     * Flash a key / value pair to the session.
     *
     * @param  string|array<string, mixed>  $key
     */
    public function with(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Session::flash($k, $v);
            }
        } else {
            Session::flash($key, $value);
        }

        return $this;
    }

    /**
     * Flash a success notification message.
     */
    public function withSuccess(string $message): static
    {
        return $this->with('success', $message);
    }

    /**
     * Flash an error notification message.
     */
    public function withError(string $message): static
    {
        return $this->with('error', $message);
    }

    /**
     * Flash a warning notification message.
     */
    public function withWarning(string $message): static
    {
        return $this->with('warning', $message);
    }

    /**
     * Flash an informational notification message.
     */
    public function withInfo(string $message): static
    {
        return $this->with('info', $message);
    }

    /**
     * Flash validation errors to the session.
     *
     * @param  array<string, mixed>|string  $errors
     */
    public function withErrors(array|string $errors, string $key = 'errors'): static
    {
        return $this->with($key, $errors);
    }

    /**
     * Flash the current request input to the session so old() can repopulate forms.
     *
     * @param  array<string, mixed>|null  $input
     */
    public function withInput(?array $input = null): static
    {
        $data = $input ?? $_POST;

        // Strip sensitive password fields
        unset($data['password'], $data['password_confirmation'], $data['current_password']);

        Session::flash('_old_input', $data);

        return $this;
    }

    /**
     * Add a header to the response.
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Add multiple headers to the response.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Set the HTTP status code.
     */
    public function status(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get target redirect URL.
     */
    public function getUrl(): string
    {
        return $this->url ?? '/';
    }

    /**
     * Get HTTP status code.
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Send the redirect response to the client.
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;
        $target = $this->getUrl();

        if ($this->controller !== null) {
            $this->controller->disableView();
            $this->controller->disableLayout();
        }

        $wantsJson = false;
        if ($this->controller !== null && method_exists($this->controller, 'wantsJson')) {
            $wantsJson = $this->controller->wantsJson();
        } elseif (function_exists('request_wants_json')) {
            $wantsJson = request_wants_json();
        }

        if ($wantsJson) {
            if (! headers_sent()) {
                http_response_code($this->status);
                header('Content-Type: application/json; charset=utf-8');
                foreach ($this->headers as $header => $val) {
                    header("{$header}: {$val}", true);
                }
            }

            echo json_encode([
                'success' => true,
                'redirect' => $target,
                'status' => $this->status,
            ], JSON_UNESCAPED_SLASHES);

            if (! defined('TESTING') && PHP_SAPI !== 'cli') {
                exit(0);
            }

            return;
        }

        if ($this->controller !== null) {
            $response = $this->controller->getResponse();
            if ($response instanceof Zend_Controller_Response_Http) {
                if ($response->canSendHeaders(false)) {
                    $response->setRedirect($target, $this->status);
                    foreach ($this->headers as $header => $val) {
                        $response->setHeader($header, $val, true);
                    }
                    $response->sendHeaders();

                    if (! defined('TESTING') && PHP_SAPI !== 'cli') {
                        exit(0);
                    }

                    return;
                }
            }
        }

        if (! headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $header => $val) {
                header("{$header}: {$val}", true);
            }
            header("Location: {$target}", true, $this->status);
        }

        if (! defined('TESTING') && PHP_SAPI !== 'cli') {
            exit(0);
        }
    }

    /**
     * Auto-send on object destruction if not explicitly executed (in web environments).
     */
    public function __destruct()
    {
        if (! $this->sent && PHP_SAPI !== 'cli' && ! defined('TESTING')) {
            $this->send();
        }
    }

    /**
     * Cast redirect response to URL string.
     */
    public function __toString(): string
    {
        return $this->getUrl();
    }
}
