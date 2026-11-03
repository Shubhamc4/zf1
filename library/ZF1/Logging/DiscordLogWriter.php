<?php

declare(strict_types=1);

namespace ZF1\Logging;

use Throwable;
use Zend_Config;
use Zend_Log;
use Zend_Log_Writer_Abstract;

final class DiscordLogWriter extends Zend_Log_Writer_Abstract
{
    private static bool $isHandling = false;

    public function __construct()
    {
        // Only accept error-level events: EMERG (0), ALERT (1), CRIT (2), ERR (3)
        $this->addFilter(Zend_Log::ERR);
    }

    /**
     * Write an error log event to Discord.
     *
     * @param  array<string, mixed>  $event
     */
    protected function _write($event): void
    {
        if (self::$isHandling) {
            return;
        }

        self::$isHandling = true;

        try {
            $rawMessage = $event['message'] ?? '';
            $priorityName = (string) ($event['priorityName'] ?? 'ERROR');
            $priority = (int) ($event['priority'] ?? Zend_Log::ERR);

            $data = is_string($rawMessage) ? json_decode($rawMessage, true) : $rawMessage;

            if (is_array($data) && isset($data['Message'])) {
                $title = sprintf('🚨 Application Error: %s', $priorityName);
                $message = (string) $data['Message'];

                // Resolve real application origin, skipping vendor files
                $origin = resolveAppOrigin($data);

                $rawFile = (string) $origin['file'];
                $line = (int) $origin['line'];

                $baseDir = base_path().DIRECTORY_SEPARATOR;
                $cleanFile = ($baseDir !== '' && str_contains($rawFile, $baseDir))
                    ? str_replace($baseDir, '', $rawFile)
                    : $rawFile;

                $fileDisplay = $line > 0 ? sprintf('%s:%d', basename($cleanFile), $line) : basename($cleanFile);

                $fields = [
                    'File' => $fileDisplay,
                    'Full Path' => $cleanFile,
                    'Code' => (string) ($data['Code'] ?? '500'),
                    'User ID' => ! empty($data['User ID']) ? (string) $data['User ID'] : 'Guest',
                    'URL' => ! empty($_SERVER['REQUEST_URI']) ? sprintf('%s %s', $_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI']) : 'CLI',
                    'IP' => (string) ($data['IP'] ?? getIpAddress()),
                    'Environment' => (string) env('APP_ENV', 'production'),
                ];

                if (! empty($data['Stack'])) {
                    $cleanStack = formatCleanTrace((string) $data['Stack']);
                    $stackLines = array_slice(explode("\n", $cleanStack), 0, 4);
                    $fields['Stack Trace'] = substr(implode("\n", $stackLines), 0, 350);
                }

                DiscordNotifier::error($title, $message, $fields);
            } else {
                $rawString = is_array($rawMessage) ? json_encode($rawMessage, JSON_UNESCAPED_SLASHES) : (string) $rawMessage;
                $cleanMessage = function_exists('formatCleanTrace') ? formatCleanTrace($rawString) : $rawString;
                $title = sprintf('🚨 Application Error: %s', $priorityName);

                DiscordNotifier::error($title, $cleanMessage, [
                    'Priority' => "{$priorityName} ({$priority})",
                    'URL' => ! empty($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : 'CLI',
                    'Environment' => env('APP_ENV', 'production'),
                ]);
            }
        } catch (Throwable) {
            // Silently ignore so log writing never breaks application flow
        } finally {
            self::$isHandling = false;
        }
    }

    /**
     * Create a new instance of DiscordLogWriter.
     *
     * @param  array|Zend_Config  $config
     * @return self
     */
    public static function factory($config)
    {
        return new self;
    }
}
