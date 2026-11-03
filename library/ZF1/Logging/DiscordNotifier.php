<?php

declare(strict_types=1);

namespace ZF1\Logging;

use Throwable;

final class DiscordNotifier
{
    public const COLOR_INFO = 3447003;     // Blue (#3498DB)

    public const COLOR_SUCCESS = 3066993;  // Green (#2ECC71)

    public const COLOR_WARNING = 16776960; // Yellow (#FFFF00)

    public const COLOR_ERROR = 15158332;   // Red (#E74C3C)

    /**
     * Send a notification to Discord webhook if configured.
     *
     * @param  string  $message  Main message text / description
     * @param  string  $level  'info', 'success', 'warning', 'error'
     * @param  array<string, mixed>  $fields  Key-value pairs for embed fields
     * @param  string|null  $title  Optional title for the embed
     * @return bool True if webhook sent successfully, false otherwise
     */
    public static function send(
        string $message,
        string $level = 'info',
        array $fields = [],
        ?string $title = null
    ): bool {
        $webhookUrl = trim((string) env('DISCORD_WEBHOOK_URL', ''));
        if (empty($webhookUrl)) {
            return false;
        }

        $color = match (strtolower($level)) {
            'success', 'ok' => self::COLOR_SUCCESS,
            'warning', 'warn' => self::COLOR_WARNING,
            'error', 'danger', 'fail', 'failed' => self::COLOR_ERROR,
            default => self::COLOR_INFO,
        };

        $embedFields = [];
        foreach ($fields as $name => $value) {
            $embedFields[] = [
                'name' => (string) $name,
                'value' => (string) (is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value),
                'inline' => true,
            ];
        }

        $embed = [
            'title' => $title ?: ucfirst($level),
            'description' => $message,
            'color' => $color,
            'timestamp' => date('c'),
            'footer' => [
                'text' => sprintf('%s • %s', env('APP_NAME', 'ZF1'), env('APP_ENV', 'production')),
            ],
        ];

        if (! empty($embedFields)) {
            $embed['fields'] = $embedFields;
        }

        $payload = json_encode([
            'username' => sprintf('%s Notifier', env('APP_NAME', 'ZF1')),
            'embeds' => [$embed],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return false;
        }

        try {
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $statusCode >= 200 && $statusCode < 300;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Send an informational Discord notification.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function info(string $title, string $message = '', array $fields = []): bool
    {
        return self::send($message ?: $title, 'info', $fields, $title);
    }

    /**
     * Send a success Discord notification.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function success(string $title, string $message = '', array $fields = []): bool
    {
        return self::send($message ?: $title, 'success', $fields, $title);
    }

    /**
     * Send a warning Discord notification.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function warning(string $title, string $message = '', array $fields = []): bool
    {
        return self::send($message ?: $title, 'warning', $fields, $title);
    }

    /**
     * Send an error Discord notification.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function error(string $title, string $message = '', array $fields = []): bool
    {
        return self::send($message ?: $title, 'error', $fields, $title);
    }
}
