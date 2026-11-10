<?php

declare(strict_types=1);

namespace ZF1\Mail;

use CommonFunction;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Zend_Mail;
use Zend_Mail_Transport_Abstract;
use Zend_Mail_Transport_Smtp;
use Zend_Mime;
use Zend_Mime_Part;
use ZF1\Foundation\Bootstrap;

/**
 * Mailer Service to send emails using Zend_Mail and environment configuration.
 */
final class Mailer
{
    /** @var Zend_Mail Underlying Zend_Mail instance */
    protected Zend_Mail $mail;

    /** @var Zend_Mail_Transport_Abstract|null Configured SMTP transport */
    protected ?Zend_Mail_Transport_Abstract $transport = null;

    /** @var string|null Sender email address */
    protected ?string $fromEmail = null;

    /** @var string|null Sender display name */
    protected ?string $fromName = null;

    /** @var string|null Email subject */
    protected ?string $subject = null;

    /** @var bool Whether email layout wrapper is enabled */
    protected bool $useLayout = true;

    /** @var array<string, mixed> Template payload variables ({name}, {{email}}, etc.) */
    protected array $payload = [];

    /** @var array<string, mixed> Data to pass into the email layout template */
    protected array $layoutData = [];

    /** @var string|null Raw subject template before interpolation */
    protected ?string $rawSubject = null;

    /** @var string|null Custom layout file path */
    protected ?string $layoutPath = null;

    /** @var string|null Raw content body before layout rendering */
    protected ?string $rawContent = null;

    /** @var string|null Charset for HTML body */
    protected ?string $htmlCharset = null;

    /** @var string Encoding for HTML body */
    protected string $htmlEncoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE;

    /** @var string|null Last error message from send() */
    public static ?string $lastError = null;

    /**
     * Create a new Mailer instance.
     */
    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null
    ) {
        $this->mail = new Zend_Mail;

        $host = $host ?? (string) env('MAIL_HOST', 'localhost');
        $port = $port ?? (int) env('MAIL_PORT', 25);
        $username = $username ?? (string) env('MAIL_USERNAME', '');
        $password = $password ?? (string) env('MAIL_PASSWORD', '');
        $ssl = $encryption ?? env('MAIL_ENCRYPTION');

        if (empty($ssl) && $port === 465) {
            $ssl = 'ssl';
        }

        $config = [
            'port' => $port,
        ];

        if ($username !== '') {
            $config['auth'] = (string) env('MAIL_AUTH', 'login');
            $config['username'] = $username;
            $config['password'] = $password;
        }

        if (! empty($ssl)) {
            $config['ssl'] = $ssl;
        }

        $this->transport = new Zend_Mail_Transport_Smtp($host, $config);
        Zend_Mail::setDefaultTransport($this->transport);

        $from = (string) (env('MAIL_FROM_ADDRESS') ?: env('MAIL_USERNAME', ''));
        $fromName = (string) env('MAIL_FROM_NAME', '');
        if ($from !== '') {
            $this->fromEmail = $from;
            $this->fromName = $fromName !== '' ? $fromName : null;
        }
    }

    /**
     * Static factory to create a new Mailer instance.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Fluent shortcut: create Mailer and add recipient.
     *
     * @param  string|array<string, string>|array<string>  $email
     */
    public static function to(string|array $email, string $name = ''): self
    {
        return (new self)->addTo($email, $name);
    }

    /**
     * Check if email sending is enabled via MAIL_ACTIVE env variable.
     */
    public static function isMailActive(): bool
    {
        $active = env('MAIL_ACTIVE', true);

        return filter_var($active, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Check if the application is running in production environment.
     */
    public static function isProduction(): bool
    {
        $env = (string) env('APP_ENV', defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production');

        return strtolower($env) === 'production';
    }

    /**
     * Set the sender email and optional name.
     */
    public function setFrom(string $email, ?string $name = null): self
    {
        $this->fromEmail = $email;
        $this->fromName = $name;

        return $this;
    }

    /**
     * Add recipient email.
     *
     * @param  string|array<string, string>|array<string>  $email
     */
    public function addTo(string|array $email, string $name = ''): self
    {
        $this->mail->addTo($email, $name);

        return $this;
    }

    /**
     * Add CC recipient email.
     *
     * @param  string|array<string, string>|array<string>  $email
     */
    public function addCc(string|array $email, string $name = ''): self
    {
        $this->mail->addCc($email, $name);

        return $this;
    }

    /**
     * Add BCC recipient email.
     *
     * @param  string|array<string>  $email
     */
    public function addBcc(string|array $email): self
    {
        $this->mail->addBcc($email);

        return $this;
    }

    /**
     * Set Reply-To email address.
     */
    public function setReplyTo(string $email, ?string $name = null): self
    {
        $this->mail->setReplyTo($email, $name);

        return $this;
    }

    /**
     * Share a global variable across all email layouts and mailers.
     *
     * @param  string|array<string, mixed>  $key
     */
    public static function share(string|array $key, mixed $value = null): void
    {
        EmailLayout::share($key, $value);
    }

    /**
     * Get all currently shared global email variables.
     *
     * @return array<string, mixed>
     */
    public static function getShared(): array
    {
        return EmailLayout::getShared();
    }

    /**
     * Flush all shared global email variables.
     */
    public static function flushShared(): void
    {
        EmailLayout::flushShared();
    }

    /**
     * Set or merge payload variables for template interpolation ({name}, {{email}}, etc.).
     *
     * @param  array<string, mixed>  $payload
     */
    public function with(array $payload): self
    {
        $this->payload = array_merge($this->payload, $payload);
        $this->refreshContentAndSubject();

        return $this;
    }

    /**
     * Fluent alias for with().
     *
     * @param  array<string, mixed>  $payload
     */
    public function withPayload(array $payload): self
    {
        return $this->with($payload);
    }

    /**
     * Replace payload variables for template interpolation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        $this->refreshContentAndSubject();

        return $this;
    }

    /**
     * Get current payload variables.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Resolve effective variables for subject interpolation.
     *
     * @return array<string, mixed>
     */
    protected function resolveSubjectVariables(): array
    {
        $merged = array_merge(EmailLayout::getShared(), $this->payload);
        $appName = (string) ($merged['app_name'] ?? $merged['appName'] ?? $merged['APP_NAME'] ?? (defined('APP_NAME') ? APP_NAME : env('APP_NAME', 'Application')));
        $appUrl = (string) ($merged['app_url'] ?? $merged['appUrl'] ?? $merged['APP_URL'] ?? rtrim((string) env('APP_URL', 'http://localhost'), '/'));

        return array_merge([
            'app_name' => $appName,
            'appName' => $appName,
            'APP_NAME' => $appName,
            'app_url' => $appUrl,
            'appUrl' => $appUrl,
            'APP_URL' => $appUrl,
            'year' => date('Y'),
            'YEAR' => date('Y'),
            'date' => date('Y-m-d'),
            'DATE' => date('Y-m-d'),
        ], $merged);
    }

    /**
     * Refresh subject and body when payload or subject changes.
     */
    protected function refreshContentAndSubject(): void
    {
        if ($this->rawSubject !== null) {
            $effectiveVars = $this->resolveSubjectVariables();
            $this->subject = EmailLayout::interpolate($this->rawSubject, $effectiveVars);
            $this->mail->clearSubject();
            $this->mail->setSubject($this->subject);
        }

        if ($this->rawContent !== null) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }
    }

    /**
     * Disable email layout rendering for this email.
     */
    public function withoutLayout(): self
    {
        $this->useLayout = false;

        if ($this->rawContent !== null) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }

        return $this;
    }

    /**
     * Enable or disable email layout rendering for this email.
     */
    public function withLayout(bool $enabled = true): self
    {
        $this->useLayout = $enabled;

        if ($this->rawContent !== null) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }

        return $this;
    }

    /**
     * Set a custom layout template path for this email.
     */
    public function setLayout(?string $layoutPath): self
    {
        $this->layoutPath = $layoutPath;

        if ($this->rawContent !== null && $this->useLayout) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }

        return $this;
    }

    /**
     * Set additional variables to be passed to the email layout template.
     *
     * @param  array<string, mixed>  $data
     */
    public function setLayoutData(array $data): self
    {
        $this->layoutData = $data;

        if ($this->rawContent !== null && $this->useLayout) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }

        return $this;
    }

    /**
     * Merge additional variables to be passed to the email layout template.
     *
     * @param  array<string, mixed>  $data
     */
    public function withLayoutData(array $data): self
    {
        $this->layoutData = array_merge($this->layoutData, $data);

        if ($this->rawContent !== null && $this->useLayout) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }

        return $this;
    }

    /**
     * Check if email layout wrapper is currently enabled.
     */
    public function isLayoutEnabled(): bool
    {
        return $this->useLayout;
    }

    /**
     * Set email subject with optional payload interpolation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setSubject(string $subject, array $payload = []): self
    {
        $this->rawSubject = $subject;
        if (! empty($payload)) {
            $this->payload = array_merge($this->payload, $payload);
        }

        $this->subject = EmailLayout::interpolate($subject, $this->resolveSubjectVariables());
        $this->mail->clearSubject();
        $this->mail->setSubject($this->subject);

        // If HTML content was already set and layout is enabled, re-render to update the layout <title>
        if ($this->rawContent !== null && $this->useLayout) {
            $rendered = $this->renderBodyHtml($this->rawContent);
            $this->mail->setBodyHtml($rendered, $this->htmlCharset, $this->htmlEncoding);
        }

        return $this;
    }

    /**
     * Get email subject.
     */
    public function getSubject(): ?string
    {
        return $this->subject ?? $this->mail->getSubject();
    }

    /**
     * Set HTML email body with automatic layout rendering and payload variable replacement.
     *
     * @param  array<string, mixed>  $payload  Variables to replace: {name}, {{email}}, etc.
     */
    public function setBodyHtml(
        string $html,
        array $payload = [],
        ?string $charset = null,
        string $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE
    ): self {
        if (! empty($payload)) {
            $this->payload = array_merge($this->payload, $payload);
        }

        $this->rawContent = $html;
        $this->htmlCharset = $charset;
        $this->htmlEncoding = $encoding;

        // If rawSubject was set before this payload was provided, re-interpolate the subject
        if ($this->rawSubject !== null) {
            $this->subject = EmailLayout::interpolate($this->rawSubject, $this->resolveSubjectVariables());
            $this->mail->clearSubject();
            $this->mail->setSubject($this->subject);
        }

        $rendered = $this->renderBodyHtml($html);
        $this->mail->setBodyHtml($rendered, $charset, $encoding);

        return $this;
    }

    /**
     * Fluent alias for setBodyHtml.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setBody(
        string $content,
        array $payload = [],
        ?string $charset = null,
        string $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE
    ): self {
        return $this->setBodyHtml($content, $payload, $charset, $encoding);
    }

    /**
     * Render HTML body through the layout system with template payload interpolation.
     */
    protected function renderBodyHtml(string $html): string
    {
        $mergedData = array_merge($this->layoutData, $this->payload);

        if (! $this->useLayout) {
            return EmailLayout::interpolate($html, array_merge(EmailLayout::getShared(), $mergedData));
        }

        if ($this->layoutPath !== null) {
            return EmailLayout::renderWithTemplate($this->layoutPath, $html, $this->subject, $mergedData);
        }

        if (class_exists('CommonFunction') && method_exists('CommonFunction', 'renderEmailLayout')) {
            $interpolated = EmailLayout::interpolate($html, array_merge(EmailLayout::getShared(), $mergedData));

            return (string) CommonFunction::renderEmailLayout($interpolated, $this->subject);
        }

        return EmailLayout::render($html, $this->subject, $mergedData);
    }

    /**
     * Render arbitrary content with the email layout system.
     *
     * @param  array<string, mixed>  $data
     */
    public static function renderLayout(string $content, ?string $subject = null, array $data = []): string
    {
        return EmailLayout::render($content, $subject, $data);
    }

    /**
     * Publish the default email layout template into the project views.
     */
    public static function publishLayout(?string $targetPath = null, bool $force = false): string
    {
        return EmailLayout::publish($targetPath, $force);
    }

    /**
     * Check if project-specific email layout is published.
     */
    public static function isLayoutPublished(?string $targetPath = null): bool
    {
        return EmailLayout::isPublished($targetPath);
    }

    /**
     * Set plain text email body with optional variable interpolation.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setBodyText(string $text, array $payload = [], ?string $charset = null, string $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE): self
    {
        if (! empty($payload)) {
            $this->payload = array_merge($this->payload, $payload);
        }

        $allVars = array_merge(EmailLayout::getShared(), $this->payload);
        $interpolated = EmailLayout::interpolate($text, $allVars);

        $this->mail->setBodyText($interpolated, $charset, $encoding);

        return $this;
    }

    /**
     * Attach a file to the email.
     *
     * @param  string|Zend_Mime_Part  $path  File path on disk or Zend_Mime_Part instance
     * @param  string|null  $filename  Custom attachment filename
     */
    public function addAttachment(string|Zend_Mime_Part $path, ?string $filename = null): self
    {
        if ($path instanceof Zend_Mime_Part) {
            $this->mail->addAttachment($path);

            return $this;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Attachment file not found or not readable: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open attachment file: {$path}");
        }

        $mimeType = function_exists('mime_content_type') ? @mime_content_type($path) : 'application/octet-stream';
        if (! $mimeType) {
            $mimeType = 'application/octet-stream';
        }

        $cleanFilename = basename($filename ?: basename($path));

        $attachment = new Zend_Mime_Part($handle);
        $attachment->type = $mimeType;
        $attachment->filename = $cleanFilename;
        $attachment->encoding = Zend_Mime::ENCODING_BASE64;
        $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;

        $this->mail->addAttachment($attachment);

        return $this;
    }

    /**
     * Get underlying Zend_Mail instance.
     */
    public function getMail(): Zend_Mail
    {
        return $this->mail;
    }

    /**
     * Get configured SMTP transport instance.
     */
    public function getTransport(): ?Zend_Mail_Transport_Abstract
    {
        return $this->transport;
    }

    /**
     * Send the email.
     *
     * @throws RuntimeException
     */
    public function send(): bool
    {
        if (empty($this->mail->getRecipients())) {
            throw new RuntimeException('addTo method is not set');
        }
        if ($this->subject === null && empty($this->mail->getSubject())) {
            throw new RuntimeException('setSubject method is not set');
        }
        if (empty($this->mail->getBodyHtml()) && empty($this->mail->getBodyText())) {
            throw new RuntimeException('setBodyHtml method is not set');
        }

        if ($this->mail->getFrom() === null && $this->fromEmail !== null) {
            $this->mail->setFrom($this->fromEmail, $this->fromName);
        }

        $effectiveSubject = (string) ($this->subject ?? $this->mail->getSubject());
        $effectiveSubject = strtr($effectiveSubject, ["\r" => '', "\n" => '']);

        $isProduction = self::isProduction();
        $testMailRaw = trim((string) env('TEST_MAIL', ''));

        if (! $isProduction && $testMailRaw !== '') {
            $rawCandidates = explode(',', $testMailRaw);
            $validTestRecipients = [];

            foreach ($rawCandidates as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }
                $isValid = function_exists('isValidEmail')
                    ? isValidEmail($candidate)
                    : (bool) filter_var($candidate, FILTER_VALIDATE_EMAIL);

                if ($isValid) {
                    $validTestRecipients[] = $candidate;
                }
            }

            if (! empty($validTestRecipients)) {
                $originalRecipients = $this->mail->getRecipients();
                $this->mail->clearRecipients();

                foreach ($validTestRecipients as $testAddress) {
                    $this->mail->addTo($testAddress, 'Test Mail');
                }

                $recipientList = implode(', ', $originalRecipients);
                $effectiveSubject = "[TEST: {$recipientList}] {$effectiveSubject}";
            } else {
                Bootstrap::debug([
                    'Time' => Bootstrap::now(),
                    'Message' => "TEST_MAIL is configured with invalid email address(es): '{$testMailRaw}'. Sending to original recipients instead.",
                ]);
            }
        }

        $this->mail->clearSubject();
        $this->mail->setSubject($effectiveSubject);

        if (empty($this->mail->getBodyText()) && $this->rawContent !== null) {
            $plain = preg_replace('/<br\s*\/?>/i', "\n", $this->rawContent);
            $plain = preg_replace('/<\/(p|div|h[1-6]|tr)>/i', "\n\n", (string) $plain);
            $plain = preg_replace('/<\/li>/i', "\n", (string) $plain);
            $plain = html_entity_decode(strip_tags((string) $plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plain = (string) preg_replace("/\n{3,}/", "\n\n", trim((string) $plain));

            if ($plain !== '') {
                $this->mail->setBodyText($plain, $this->htmlCharset);
            }
        }

        if (! self::isMailActive()) {
            Bootstrap::debug([
                'Time' => Bootstrap::now(),
                'Message' => 'Mail sending skipped: MAIL_ACTIVE is false',
                'Subject' => $this->mail->getSubject(),
                'Recipients' => $this->mail->getRecipients(),
            ]);

            return true;
        }

        try {
            self::$lastError = null;
            $this->mail->send($this->transport);

            return true;
        } catch (Throwable $th) {
            self::$lastError = $th->getMessage();

            Bootstrap::debug([
                'Time' => Bootstrap::now(),
                'User ID' => self::resolveUserId(),
                'IP' => php_sapi_name() === 'cli' ? '127.0.0.1' : (function_exists('getIpAddress') ? getIpAddress() : '127.0.0.1'),
                'Device' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'Code' => $th->getCode(),
                'File' => $th->getFile(),
                'Line' => $th->getLine(),
                'Message' => $th->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve current user ID safely.
     */
    private static function resolveUserId(): ?string
    {
        foreach (['\App\Bootstrap', '\Bootstrap', 'Bootstrap'] as $class) {
            if (class_exists($class, false) && property_exists($class, 'loginSession')) {
                $session = $class::$loginSession;
                if ($session && isset($session->id) && $session->id) {
                    return (string) $session->id;
                }
            }
        }

        return null;
    }
}
