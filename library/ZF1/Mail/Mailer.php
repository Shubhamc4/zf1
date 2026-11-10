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
     * Set email subject.
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

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
     * Set HTML email body with optional layout rendering.
     */
    public function setBodyHtml(string $html, ?string $charset = null, string $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE): self
    {
        if (class_exists('CommonFunction') && method_exists('CommonFunction', 'renderEmailLayout')) {
            $html = (string) CommonFunction::renderEmailLayout($html);
        }

        $this->mail->setBodyHtml($html, $charset, $encoding);

        return $this;
    }

    /**
     * Set plain text email body.
     */
    public function setBodyText(string $text, ?string $charset = null, string $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE): self
    {
        $this->mail->setBodyText($text, $charset, $encoding);

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

        if (! file_exists($path) || ! is_readable($path)) {
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

        $attachment = new Zend_Mime_Part($handle);
        $attachment->type = $mimeType;
        $attachment->filename = $filename ?: basename($path);
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

        // Apply From address if not yet set
        if ($this->mail->getFrom() === null && $this->fromEmail !== null) {
            $this->mail->setFrom($this->fromEmail, $this->fromName);
        }

        $effectiveSubject = $this->subject ?? (string) $this->mail->getSubject();

        // Handle TEST_MAIL override: route all mail to test address and annotate subject
        $testMail = trim((string) env('TEST_MAIL', ''));
        if ($testMail !== '') {
            $originalRecipients = $this->mail->getRecipients();
            $this->mail->clearRecipients();
            $this->mail->addTo($testMail, 'Test Mail');

            $recipientList = implode(', ', $originalRecipients);
            $effectiveSubject = "[TEST: {$recipientList}] {$effectiveSubject}";
        }

        if ($this->mail->getSubject() === null) {
            $this->mail->setSubject($effectiveSubject);
        }

        // If MAIL_ACTIVE is false, log and skip actual network transmission
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
