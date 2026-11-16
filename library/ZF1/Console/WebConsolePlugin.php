<?php

declare(strict_types=1);

namespace ZF1\Console;

use AllowDynamicProperties;
use Zend_Controller_Plugin_Abstract;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;

/**
 * Front Controller Plugin for intercepting and handling Web Console requests.
 */
#[AllowDynamicProperties]
final class WebConsolePlugin extends Zend_Controller_Plugin_Abstract
{
    /**
     * Intercept request before router evaluates it.
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request): void
    {
        if (! ($request instanceof Zend_Controller_Request_Http)) {
            return;
        }

        $uri = $request->getRequestUri();

        if (! WebConsole::matchesPath($uri)) {
            return;
        }

        if (! WebConsole::isEnabled()) {
            return;
        }

        $isJson = $request->isXmlHttpRequest() || WebConsole::isExecuteRequest($uri)
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if (! WebConsole::checkIp()) {
            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'status' => 403,
                    'error' => 'Access denied: Client IP address not authorized to access web console.',
                ], JSON_UNESCAPED_SLASHES);
                exit(0);
            }

            $redirectUrl = WebConsole::resolveForbiddenRedirect();
            if (class_exists(\ZF1\Session\Session::class)) {
                \ZF1\Session\Session::flash('error', 'Access denied: Client IP address not authorized to access web console.');
            }

            if (! headers_sent()) {
                http_response_code(302);
                header("Location: {$redirectUrl}", true, 302);
            }
            exit(0);
        }

        if (! WebConsole::checkAuth($request)) {
            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'status' => 403,
                    'error' => 'Access denied. Administrator privileges required.',
                ], JSON_UNESCAPED_SLASHES);
                exit(0);
            }

            $redirectUrl = WebConsole::resolveForbiddenRedirect();
            if (class_exists(\ZF1\Session\Session::class)) {
                \ZF1\Session\Session::flash('error', 'Access denied. Administrator privileges required.');
            }

            if (! headers_sent()) {
                http_response_code(302);
                header("Location: {$redirectUrl}", true, 302);
            }
            exit(0);
        }

        $response = $this->getResponse();
        if (! ($response instanceof Zend_Controller_Response_Http)) {
            $response = new Zend_Controller_Response_Http;
        }

        WebConsole::handle($request, $response);
    }
}
