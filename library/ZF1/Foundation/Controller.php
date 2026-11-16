<?php

declare(strict_types=1);

namespace ZF1\Foundation;

use AllowDynamicProperties;
use RuntimeException;
use Zend_Controller_Action;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;
use Zend_Layout;
use Zend_View;
use Zend_View_Interface;
use ZF1\Session\Session;
use ZF1\Validation\ValidationException;
use ZF1\Validation\Validator;

/**
 * Modern Base Action Controller for Zend Framework 1.
 *
 * @property Zend_View|Zend_View_Interface $view
 * @property Zend_Controller_Action_HelperBroker $_helper
 * @property Zend_Controller_Request_Http $_request
 * @property Zend_Controller_Response_Http $_response
 *
 * @method Zend_Controller_Request_Http getRequest()
 * @method Zend_Controller_Response_Http getResponse()
 */
#[AllowDynamicProperties]
abstract class Controller extends Zend_Controller_Action
{
    /**
     * Cached JSON request payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $jsonPayload = null;

    /**
     * Disable Zend_Layout for the current request.
     */
    public function disableLayout(): void
    {
        if (class_exists('Zend_Layout', false)) {
            $layout = Zend_Layout::getMvcInstance();
            if ($layout instanceof Zend_Layout) {
                $layout->disableLayout();
            }
        }

        if ($this->_helper && $this->_helper->hasHelper('layout')) {
            $this->_helper->layout->disableLayout();
        }
    }

    /**
     * Disable Zend ViewRenderer (prevents auto-rendering .phtml).
     */
    public function disableView(): void
    {
        if ($this->_helper->hasHelper('viewRenderer')) {
            $this->_helper->viewRenderer->setNoRender(true);
        }
    }

    /**
     * Check if the incoming request expects a JSON response.
     */
    public function wantsJson(): bool
    {
        $request = $this->getRequest();
        if (! ($request instanceof Zend_Controller_Request_Http)) {
            return false;
        }

        return $request->isXmlHttpRequest()
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'));
    }

    /**
     * Retrieve the parsed JSON request payload (from php://input).
     */
    public function jsonPayload(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonPayload === null) {
            $raw = (string) file_get_contents('php://input');
            if (trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                $this->jsonPayload = is_array($decoded) ? $decoded : [];
            } else {
                $this->jsonPayload = [];
            }
        }

        if ($key === null) {
            return $this->jsonPayload;
        }

        return function_exists('data_get')
            ? data_get($this->jsonPayload, $key, $default)
            : ($this->jsonPayload[$key] ?? $default);
    }

    /**
     * Retrieve a request parameter from GET, POST, Route, or JSON payload.
     */
    public function param(string $key, mixed $default = null, bool $sanitize = false): mixed
    {
        $value = $this->getParam($key);

        if ($value === null && $this->jsonPayload !== null && array_key_exists($key, $this->jsonPayload)) {
            $value = $this->jsonPayload[$key];
        }

        if ($value === null) {
            $value = $this->jsonPayload($key, $default);
        }

        if ($value === null) {
            $value = $default;
        }

        if ($sanitize && is_string($value) && function_exists('sanitizeInput')) {
            return sanitizeInput($value);
        }

        return $value;
    }

    /**
     * Send a JSON response with status code and headers.
     *
     * @param  array<string, string>  $headers
     */
    public function json(mixed $data, int $status = 200, array $headers = []): void
    {
        $this->disableLayout();
        $this->disableView();

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $response = $this->getResponse();
        if ($response instanceof Zend_Controller_Response_Http) {
            $response->setHttpResponseCode($status);
            $response->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            foreach ($headers as $k => $v) {
                $response->setHeader($k, $v, true);
            }
            $response->setBody(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $response->sendResponse();
            exit(0);
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    /**
     * Send a standardized successful JSON API response.
     *
     * @param  array<string, mixed>  $extra
     */
    public function success(mixed $data = [], string $message = 'Success', int $status = 200, array $extra = []): void
    {
        $payload = array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $extra);

        $this->json($payload, $status);
    }

    /**
     * Send a standardized error JSON API response.
     *
     * @param  array<string, mixed>  $errors
     */
    public function error(string $message = 'Error', int $status = 400, array $errors = [], ?string $errorCode = null): void
    {
        $payload = [
            'success' => false,
            'error' => $message,
            'status' => $status,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        if ($errorCode !== null) {
            $payload['code'] = $errorCode;
        }

        $this->json($payload, $status);
    }

    /**
     * Stream a file download to the client browser.
     *
     * @param  array<string, string>  $headers
     */
    public function download(string $filePath, ?string $filename = null, array $headers = []): void
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException("File not found or unreadable: [{$filePath}].");
        }

        $this->disableLayout();
        $this->disableView();

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $name = $filename ?: basename($filePath);
        $size = filesize($filePath);
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        header('Content-Type: '.$mime);
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="'.addslashes($name).'"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        if ($size !== false) {
            header('Content-Length: '.$size);
        }

        foreach ($headers as $k => $v) {
            header("{$k}: {$v}");
        }

        readfile($filePath);
        exit(0);
    }

    /**
     * Serve a file inline to the client browser.
     */
    public function file(string $filePath, ?string $contentType = null): void
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException("File not found: [{$filePath}].");
        }

        $this->disableLayout();
        $this->disableView();

        $mime = $contentType ?: (mime_content_type($filePath) ?: 'application/octet-stream');
        $size = filesize($filePath);

        header('Content-Type: '.$mime);
        if ($size !== false) {
            header('Content-Length: '.$size);
        }
        header('Cache-Control: public, max-age=86400');

        readfile($filePath);
        exit(0);
    }

    /**
     * Validate input parameters using the ZF1 Validation Engine.
     * Automatically handles JSON errors (422) or redirects back with errors & old input.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>|array<string, mixed>  $messages
     * @param  array<string, string>  $customAttributes
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed> Validated data
     *
     * @throws ValidationException
     */
    public function validate(
        array $rules,
        array $messages = [],
        array $customAttributes = [],
        ?array $data = null
    ): array {
        // Backward compatibility: If 2nd argument contains data instead of error messages
        if ($data === null && ! empty($messages) && ! empty(array_intersect_key($rules, $messages))) {
            $data = $messages;
            $messages = [];
        }

        $input = $data !== null ? $data : array_merge($this->getRequest()->getParams(), $this->jsonPayload() ?: []);

        $validator = Validator::make($input, $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            if ($this->wantsJson()) {
                $this->error('The given data was invalid.', 422, $validator->errors()->toArray());
            }

            $this->back()->withErrors($validator->errors()->toArray())->withInput()->send();

            throw new ValidationException($validator->errors(), $validator);
        }

        return $validator->validated();
    }

    /**
     * Create a new Validator instance without immediately throwing or redirecting.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $customAttributes
     */
    public function validator(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): Validator {
        return Validator::make($data, $rules, $messages, $customAttributes);
    }

    /**
     * Create a fluent redirect response to a URL, route, or action.
     * Compatible with Zend_Controller_Action::redirect($url, array $options = []).
     *
     * @param  string|null  $url  Target URL or route
     * @param  array<string, mixed>|int  $options  Array options or HTTP status code
     */
    public function redirect(mixed $url = null, array|int $options = []): RedirectResponse
    {
        $status = 302;
        if (is_int($options)) {
            $status = $options;
        } elseif (is_array($options) && isset($options['code'])) {
            $status = (int) $options['code'];
        }

        $target = is_string($url) ? $url : null;

        return new RedirectResponse($target, $status, $this);
    }

    /**
     * Redirect to the previous URL (HTTP_REFERER) or a given fallback.
     */
    public function back(?string $fallback = '/', int $status = 302): RedirectResponse
    {
        return (new RedirectResponse(null, $status, $this))->back($fallback, $status);
    }

    /**
     * Flash a key / value notification message to the session for the next request.
     */
    public function withFlash(string $key, mixed $value = true): static
    {
        Session::flash($key, $value);

        return $this;
    }

    /**
     * Retrieve flashed old input from the session.
     */
    public function old(?string $key = null, mixed $default = null): mixed
    {
        return function_exists('old') ? old($key, $default) : Session::get('_old_input.'.$key, $default);
    }

    /**
     * Provide access to optional objects or run callback when value is not null.
     */
    public function optional(mixed $value = null, ?callable $callback = null): mixed
    {
        return function_exists('optional') ? optional($value, $callback) : ($callback ? ($value !== null ? $callback($value) : null) : new \ZF1\Support\Optional($value));
    }
}
