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

        if (! WebConsole::checkIp()) {
            if ($redirectUrl = WebConsole::resolveForbiddenRedirect()) {
                redirect($redirectUrl)->with('error', 'Access denied: Client IP address not authorized to access web console.');
            }

            abort(403, 'Access denied: Client IP address not authorized to access web console.');
        }

        if (! WebConsole::checkAuth($request)) {
            if ($redirectUrl = WebConsole::resolveForbiddenRedirect()) {
                redirect($redirectUrl)->with('error', 'Access denied. Administrator privileges required.');
            }

            abort(403, 'Access denied. Administrator privileges required.');
        }

        $response = $this->getResponse();
        if (! ($response instanceof Zend_Controller_Response_Http)) {
            $response = new Zend_Controller_Response_Http;
        }

        WebConsole::handle($request, $response);
    }
}
