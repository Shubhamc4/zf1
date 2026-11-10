<?php

declare(strict_types=1);

namespace ZF1\Mail;

use RuntimeException;
use Stringable;
use Throwable;
use UnitEnum;
use ZF1\Console\Command;

/**
 * Email Layout Renderer & Publisher for ZF1.
 *
 * Renders HTML emails inside a responsive card layout with header and footer.
 * Supports published project-specific layouts, default framework fallback,
 * logo fallback hierarchy (logo.png -> logo-dark.png -> logo-light.png -> APP_NAME),
 * and secure template variable interpolation ({name}, {{email}}, {app_name}, {year}, etc.).
 */
final class EmailLayout
{
    /** @var string|null Custom project layout path override */
    protected static ?string $customProjectPath = null;

    /** @var array<string, mixed> Globally shared variables across all email layouts */
    protected static array $shared = [];

    /** @var array{0: bool, 1: ?string}|null Static cache for resolved logo [hasLogo, logoUrl] */
    protected static ?array $resolvedLogoCache = null;

    /**
     * Share global variable(s) accessible across all email templates and layouts.
     *
     * @param  string|array<string, mixed>  $key
     */
    public static function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            self::$shared = array_merge(self::$shared, $key);
        } else {
            self::$shared[$key] = $value;
        }
    }

    /**
     * Get all currently shared global variables.
     *
     * @return array<string, mixed>
     */
    public static function getShared(): array
    {
        return self::$shared;
    }

    /**
     * Flush all shared global variables.
     */
    public static function flushShared(): void
    {
        self::$shared = [];
        self::$resolvedLogoCache = null;
    }

    /**
     * Flush the cached logo resolution result.
     */
    public static function flushLogoCache(): void
    {
        self::$resolvedLogoCache = null;
    }

    /**
     * Set a custom path for the project-specific layout file.
     */
    public static function setProjectPath(?string $path): void
    {
        self::$customProjectPath = $path;
    }

    /**
     * Get the target path for the project-level published email layout.
     */
    public static function getProjectPath(): string
    {
        if (self::$customProjectPath !== null && self::$customProjectPath !== '') {
            return self::$customProjectPath;
        }

        if (function_exists('app_path')) {
            return app_path('views/scripts/emails/layout.phtml');
        }

        if (defined('APPLICATION_PATH')) {
            return APPLICATION_PATH.'/views/scripts/emails/layout.phtml';
        }

        if (function_exists('base_path')) {
            return base_path('application/views/scripts/emails/layout.phtml');
        }

        return getcwd().'/application/views/scripts/emails/layout.phtml';
    }

    /**
     * Determine if a project-specific email layout has been published.
     */
    public static function isPublished(?string $targetPath = null): bool
    {
        $path = $targetPath ?: self::getProjectPath();

        return is_file($path) && is_readable($path);
    }

    /**
     * Publish the default email layout template into the project views directory.
     *
     * @param  string|null  $targetPath  Optional explicit destination path
     * @param  bool  $force  Whether to overwrite an existing layout file
     * @return string Path of the published layout template
     *
     * @throws RuntimeException
     */
    public static function publish(?string $targetPath = null, bool $force = false): string
    {
        $target = $targetPath ?: self::getProjectPath();

        if (is_file($target) && ! $force) {
            throw new RuntimeException("Email layout already exists at [{$target}]. Use --force to overwrite.");
        }

        $dir = dirname($target);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new RuntimeException("Failed to create directory [{$dir}] for email layout.");
            }
        }

        $stub = self::getDefaultTemplate();
        if ($stub === '') {
            throw new RuntimeException('Unable to resolve default email layout template stub.');
        }

        if (file_put_contents($target, $stub) === false) {
            throw new RuntimeException("Failed to write email layout to [{$target}].");
        }

        return $target;
    }

    /**
     * Resolve the framework default template string.
     */
    public static function getDefaultTemplate(): string
    {
        if (class_exists(Command::class)) {
            try {
                return Command::resolveStub('email-layout.phtml.stub');
            } catch (Throwable) {
            }
        }

        $paths = [
            __DIR__.'/stubs/layout.phtml.stub',
            dirname(__DIR__).'/Console/stubs/email-layout.phtml.stub',
        ];

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                return (string) file_get_contents($path);
            }
        }

        return '';
    }

    /**
     * Resolve the framework default template file path.
     */
    public static function getDefaultTemplatePath(): string
    {
        if (function_exists('base_path')) {
            $customStub = base_path('stubs/email-layout.phtml.stub');
            if (is_file($customStub) && is_readable($customStub)) {
                return $customStub;
            }
        }

        $paths = [
            __DIR__.'/stubs/layout.phtml.stub',
            dirname(__DIR__).'/Console/stubs/email-layout.phtml.stub',
        ];

        foreach ($paths as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return $paths[0];
    }

    /**
     * Resolve application logo with fallback hierarchy:
     * 1. Explicit logo passed in payload / data
     * 2. logo.png
     * 3. logo-dark.png
     * 4. logo-light.png
     * 5. If nothing found, returns [false, null] to fallback to APP_NAME.
     *
     * @param  string|null  $explicitLogo  Custom logo path or URL from payload
     * @param  string|null  $appUrl  Application URL
     * @return array{0: bool, 1: ?string} [hasLogo, logoUrl]
     */
    public static function resolveLogo(?string $explicitLogo = null, ?string $appUrl = null): array
    {
        $appUrl = $appUrl ?? rtrim((string) env('APP_URL', 'http://localhost'), '/');

        if ($explicitLogo !== null && $explicitLogo !== '') {
            $cleanLogo = self::sanitizeUrl($explicitLogo);
            if ($cleanLogo === null) {
                return [false, null];
            }

            $logoUrl = str_starts_with($cleanLogo, 'http://') || str_starts_with($cleanLogo, 'https://')
                ? $cleanLogo
                : $appUrl.'/'.ltrim($cleanLogo, '/');

            return [true, $logoUrl];
        }

        if (self::$resolvedLogoCache !== null) {
            return self::$resolvedLogoCache;
        }

        $candidates = [
            'logo.png',
            'logo-dark.png',
            'logo-light.png',
        ];

        if (function_exists('public_path')) {
            foreach ($candidates as $candidate) {
                $path = public_path($candidate);
                if (is_file($path) && is_readable($path)) {
                    self::$resolvedLogoCache = [true, $appUrl.'/'.ltrim($candidate, '/')];

                    return self::$resolvedLogoCache;
                }
            }

            foreach (['images/', 'img/'] as $sub) {
                foreach ($candidates as $candidate) {
                    $rel = $sub.$candidate;
                    $path = public_path($rel);
                    if (is_file($path) && is_readable($path)) {
                        self::$resolvedLogoCache = [true, $appUrl.'/'.$rel];

                        return self::$resolvedLogoCache;
                    }
                }
            }
        }

        self::$resolvedLogoCache = [false, null];

        return self::$resolvedLogoCache;
    }

    /**
     * Sanitize URL to disallow dangerous schemes (javascript:, vbscript:, data:text/html).
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $trimmed = trim($url);
        $lower = strtolower($trimmed);

        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:') || str_starts_with($lower, 'data:text/html')) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Replace template variables in body or subject: {name}, {{email}}, {{ app_name }}, etc.
     * Supports scalar values, Stringable objects, and enums.
     *
     * @param  array<string, mixed>  $variables
     */
    public static function interpolate(string $text, array $variables): string
    {
        if ($text === '' || empty($variables)) {
            return $text;
        }

        $lookup = [];

        foreach ($variables as $key => $value) {
            $valStr = self::stringifyValue($value);
            if ($valStr === null) {
                continue;
            }

            $cleanKey = strtolower(trim((string) $key));
            $lookup[$cleanKey] = $valStr;
        }

        foreach ($variables as $key => $value) {
            $valStr = self::stringifyValue($value);
            if ($valStr === null) {
                continue;
            }

            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $key));
            if (! array_key_exists($snake, $lookup)) {
                $lookup[$snake] = $valStr;
            }

            $noUnderscore = str_replace('_', '', $snake);
            if (! array_key_exists($noUnderscore, $lookup)) {
                $lookup[$noUnderscore] = $valStr;
            }
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_\-\.]+)\s*\}\}|\{\s*([a-zA-Z0-9_\-\.]+)\s*\}/',
            static function (array $matches) use ($lookup) {
                $key = $matches[1] !== '' ? $matches[1] : $matches[2];
                $normalized = strtolower(trim($key));

                if (array_key_exists($normalized, $lookup)) {
                    return $lookup[$normalized];
                }

                $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
                if (array_key_exists($snake, $lookup)) {
                    return $lookup[$snake];
                }

                $noUnderscore = str_replace('_', '', $snake);
                if (array_key_exists($noUnderscore, $lookup)) {
                    return $lookup[$noUnderscore];
                }

                return $matches[0];
            },
            $text
        );
    }

    /**
     * Safely stringify a template variable value.
     */
    protected static function stringifyValue(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof UnitEnum) {
            return isset($value->value) ? (string) $value->value : $value->name;
        }

        if ($value instanceof Stringable || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Render email content inside the email layout securely.
     *
     * Prevents double-interpolation / template injection by isolating user content
     * from layout placeholder interpolation.
     *
     * @param  string  $content  HTML or plain text body
     * @param  string|null  $subject  Email subject for title
     * @param  array<string, mixed>  $data  Payload and extra variables to pass to the layout
     * @return string Complete HTML email document
     */
    public static function render(string $content, ?string $subject = null, array $data = []): string
    {
        $variables = self::buildVariables($content, $subject, $data);

        if (self::isFullHtmlDocument($content)) {
            return self::interpolate($content, $variables);
        }

        $projectPath = self::getProjectPath();
        if (is_file($projectPath) && is_readable($projectPath)) {
            return self::renderLayoutSafely($projectPath, $variables);
        }

        $defaultPath = self::getDefaultTemplatePath();
        if (is_file($defaultPath) && is_readable($defaultPath)) {
            return self::renderLayoutSafely($defaultPath, $variables);
        }

        return self::renderDefaultFallback($variables);
    }

    /**
     * Render email content with a specific custom layout template file.
     *
     * @param  string  $templatePath  Path to the phtml template file
     * @param  string  $content  HTML or plain text body
     * @param  string|null  $subject  Email subject for title
     * @param  array<string, mixed>  $data  Payload and extra variables to pass to the layout
     */
    public static function renderWithTemplate(string $templatePath, string $content, ?string $subject = null, array $data = []): string
    {
        $variables = self::buildVariables($content, $subject, $data);

        if (self::isFullHtmlDocument($content)) {
            return self::interpolate($content, $variables);
        }

        $ext = strtolower(pathinfo($templatePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['phtml', 'php', 'html'], true)) {
            throw new RuntimeException("Invalid email layout template extension. Expected .phtml, .php, or .html, got [{$templatePath}].");
        }

        if (! is_file($templatePath) || ! is_readable($templatePath)) {
            throw new RuntimeException("Email layout template file not found or not readable: [{$templatePath}]");
        }

        return self::renderLayoutSafely($templatePath, $variables);
    }

    /**
     * Safely render a layout template file and interpolate layout placeholders
     * without recursively interpolating user-supplied content.
     *
     * @param  array<string, mixed>  $variables
     */
    protected static function renderLayoutSafely(string $templateFile, array $variables): string
    {
        $interpolatedContent = (string) ($variables['content'] ?? '');

        // Isolate user content with an unguessable single-use slot token to prevent nested template injection
        $contentSlot = '__ZF1_LAYOUT_SLOT_'.bin2hex(random_bytes(12)).'__';

        $layoutVars = array_merge($variables, ['content' => $contentSlot]);
        $renderedLayout = self::renderFile($templateFile, $layoutVars);

        $interpolatedLayout = self::interpolate($renderedLayout, $variables);

        return str_replace($contentSlot, $interpolatedContent, $interpolatedLayout);
    }

    /**
     * Check if a content string is already a complete HTML document.
     */
    public static function isFullHtmlDocument(string $content): bool
    {
        return (bool) preg_match('/<\s*(?:!doctype\s+html|html)[\s>]/i', $content);
    }

    /**
     * Format raw text content into paragraph tags if not already HTML.
     */
    public static function prepareContent(string $content): string
    {
        if (! preg_match('/<[a-z][\s\S]*>/i', $content)) {
            return '<p style="margin: 0 0 16px; line-height: 1.65; color: #4a5568;">'.nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')).'</p>';
        }

        return $content;
    }

    /**
     * Build the standard variables array passed to the layout template and for interpolation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function buildVariables(string $content, ?string $subject = null, array $payload = []): array
    {
        $merged = array_merge(self::$shared, $payload);

        $appName = (string) ($merged['app_name'] ?? $merged['appName'] ?? $merged['APP_NAME'] ?? (defined('APP_NAME') ? APP_NAME : env('APP_NAME', 'Application')));
        $rawAppUrl = (string) ($merged['app_url'] ?? $merged['appUrl'] ?? $merged['APP_URL'] ?? rtrim((string) env('APP_URL', 'http://localhost'), '/'));
        $appUrl = self::sanitizeUrl($rawAppUrl) ?? 'http://localhost';

        $explicitLogo = $merged['logo'] ?? $merged['logoUrl'] ?? $merged['logo_url'] ?? null;
        [$hasLogo, $logoUrl] = self::resolveLogo($explicitLogo !== null ? (string) $explicitLogo : null, $appUrl);

        if (isset($merged['hasLogo']) && ! $merged['hasLogo']) {
            $hasLogo = false;
            $logoUrl = null;
        }

        $standardVars = [
            'app_name' => $appName,
            'appName' => $appName,
            'APP_NAME' => $appName,
            'app_url' => $appUrl,
            'appUrl' => $appUrl,
            'APP_URL' => $appUrl,
            'has_logo' => $hasLogo,
            'hasLogo' => $hasLogo,
            'logo_url' => $logoUrl,
            'logoUrl' => $logoUrl,
            'logo' => $logoUrl,
            'LOGO' => $logoUrl,
            'year' => date('Y'),
            'YEAR' => date('Y'),
            'date' => date('Y-m-d'),
            'DATE' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'TIME' => date('H:i:s'),
        ];

        if ($subject !== null) {
            $standardVars['subject'] = $subject;
            $standardVars['SUBJECT'] = $subject;
        }

        $allVars = array_merge($standardVars, $merged);

        $interpolatedSubject = $subject !== null ? self::interpolate($subject, $allVars) : null;
        $preparedContent = self::prepareContent($content);
        $interpolatedContent = self::interpolate($preparedContent, $allVars);

        return array_merge($allVars, [
            'content' => $interpolatedContent,
            'subject' => $interpolatedSubject,
            'appName' => $appName,
            'appUrl' => $appUrl,
            'hasLogo' => $hasLogo,
            'logoUrl' => $logoUrl,
        ]);
    }

    /**
     * Render the fallback inline HTML layout if template files cannot be read.
     *
     * @param  array<string, mixed>  $variables
     */
    protected static function renderDefaultFallback(array $variables): string
    {
        $hasLogo = ! empty($variables['hasLogo']) && ! empty($variables['logoUrl']);
        $logoUrl = (string) ($variables['logoUrl'] ?? '');
        $appName = (string) ($variables['appName'] ?? 'Application');
        $appUrl = (string) ($variables['appUrl'] ?? 'http://localhost');
        $content = (string) ($variables['content'] ?? '');

        return '<!DOCTYPE html><html><body style="background:#edf2f7;padding:32px 16px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'
            .'<div style="max-width:570px;margin:0 auto;text-align:center;padding-bottom:24px;">'
            .($hasLogo ? '<a href="'.htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8').'" target="_blank"><img src="'.htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($appName, ENT_QUOTES, 'UTF-8').'" style="max-height:50px;"></a>' : '<span style="font-size:20px;font-weight:700;color:#2d3748;">'.htmlspecialchars($appName, ENT_QUOTES, 'UTF-8').'</span>')
            .'</div>'
            .'<div style="max-width:570px;margin:0 auto;background:#fff;border:1px solid #e8e5ef;border-radius:8px;padding:40px;color:#4a5568;line-height:1.65;font-size:16px;">'
            .$content
            .'</div>'
            .'<div style="max-width:570px;margin:0 auto;text-align:center;padding-top:24px;font-size:12px;color:#a0aec0;">&copy; '.date('Y').' <a href="'.htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8').'" target="_blank" style="color:#a0aec0;text-decoration:none;">'.htmlspecialchars($appName, ENT_QUOTES, 'UTF-8').'</a>. All rights reserved.</div>'
            .'</body></html>';
    }

    /**
     * Render a PHP/PHTML template file in an isolated scope with output buffering.
     *
     * @param  array<string, mixed>  $variables
     */
    protected static function renderFile(string $file, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }
}
