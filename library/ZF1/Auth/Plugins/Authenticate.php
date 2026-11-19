<?php

declare(strict_types=1);

namespace ZF1\Auth\Plugins;

use AllowDynamicProperties;
use Zend_Controller_Plugin_Abstract;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;
use ZF1\Auth\Auth;

/**
 * Authentication Middleware / Front Controller Plugin.
 * Intercepts requests to protected routes and redirects guests to login with intended URL tracking.
 */
#[AllowDynamicProperties]
class Authenticate extends Zend_Controller_Plugin_Abstract
{
    /**
     * URL to redirect unauthenticated guests to.
     */
    protected string $loginUrl = '/login';

    /**
     * List of path patterns or module/controller/action routes excluded from authentication (public).
     *
     * @var array<string>
     */
    protected array $except = [
        'login',
        'auth/*',
        'login/*',
        'register',
        'register/*',
        'password/*',
    ];

    /**
     * List of path patterns or module/controller/action routes that strictly require authentication.
     * When non-empty, only matching routes will be protected.
     *
     * @var array<string>
     */
    protected array $only = [];

    /**
     * Create a new Authenticate plugin instance.
     *
     * @param  string  $loginUrl  Target login route
     * @param  array<string>  $except  Routes excluded from auth
     * @param  array<string>  $only  Routes strictly requiring auth
     */
    public function __construct(string $loginUrl = '/login', array $except = [], array $only = [])
    {
        $this->loginUrl = $loginUrl;

        if (! empty($except)) {
            $this->except = array_merge($this->except, $except);
        }

        if (! empty($only)) {
            $this->only = $only;
        }
    }

    /**
     * Exclude routes from authentication.
     *
     * @param  array<string>|string  $routes
     */
    public function except(array|string $routes): static
    {
        $routes = (array) $routes;
        $this->except = array_merge($this->except, $routes);

        return $this;
    }

    /**
     * Specify routes that strictly require authentication.
     *
     * @param  array<string>|string  $routes
     */
    public function only(array|string $routes): static
    {
        $this->only = (array) $routes;

        return $this;
    }

    /**
     * Set the login redirection URL.
     */
    public function setLoginUrl(string $url): static
    {
        $this->loginUrl = $url;

        return $this;
    }

    /**
     * Intercept request before action dispatch.
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request): void
    {
        if (! ($request instanceof Zend_Controller_Request_Http)) {
            return;
        }

        if (Auth::check()) {
            return;
        }

        if (! $this->shouldAuthenticate($request)) {
            return;
        }

        if ($request->isXmlHttpRequest() || (function_exists('request_wants_json') && request_wants_json())) {
            if (! headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
            }

            echo json_encode([
                'success' => false,
                'message' => 'Unauthenticated.',
                'status' => 401,
            ]);

            if (! defined('TESTING') && PHP_SAPI !== 'cli') {
                exit(0);
            }

            return;
        }

        redirect()->guest($this->loginUrl)->send();
    }

    /**
     * Determine if the request matches the protected route rules.
     */
    protected function shouldAuthenticate(Zend_Controller_Request_Http $request): bool
    {
        $path = trim($request->getPathInfo(), '/');
        $module = $request->getModuleName() ?: 'default';
        $controller = $request->getControllerName() ?: 'index';
        $action = $request->getActionName() ?: 'index';

        $routePatterns = [
            $path,
            "{$module}/{$controller}/{$action}",
            "{$module}/{$controller}/*",
            "{$controller}/{$action}",
            "{$controller}/*",
        ];

        if (! empty($this->only)) {
            $matched = false;
            foreach ($this->only as $pattern) {
                if ($this->matchesPattern($pattern, $routePatterns, $path)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        foreach ($this->except as $pattern) {
            if ($this->matchesPattern($pattern, $routePatterns, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a given pattern matches the request path or route coordinates.
     *
     * @param  array<string>  $routePatterns
     */
    protected function matchesPattern(string $pattern, array $routePatterns, string $path): bool
    {
        $pattern = trim($pattern, '/');

        foreach ($routePatterns as $candidate) {
            if (fnmatch($pattern, $candidate, FNM_CASEFOLD)) {
                return true;
            }
        }

        return fnmatch($pattern, $path, FNM_CASEFOLD);
    }
}
