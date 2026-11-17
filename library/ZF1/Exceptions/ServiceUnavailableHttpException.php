<?php

declare(strict_types=1);

namespace ZF1\Exceptions;

use Throwable;
use ZF1\System\MaintenanceMode;

class ServiceUnavailableHttpException extends HttpException
{
    protected ?int $retryAfter = null;

    /**
     * @param  array<string, string>  $headers
     * @param  int|null  $retryAfter  Seconds before client should retry
     */
    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        ?int $retryAfter = null,
        int $code = 0
    ) {
        if ($retryAfter !== null) {
            $this->retryAfter = $retryAfter;
            $headers['Retry-After'] = (string) $retryAfter;
        } elseif (! isset($headers['Retry-After']) && class_exists(MaintenanceMode::class) && MaintenanceMode::isDown()) {
            $data = MaintenanceMode::getData() ?: [];
            $retry = (int) ($data['retry'] ?? 60);
            $this->retryAfter = $retry;
            $headers['Retry-After'] = (string) $retry;
        }

        if ($message === '' && class_exists(MaintenanceMode::class) && MaintenanceMode::isDown()) {
            $data = MaintenanceMode::getData() ?: [];
            if (! empty($data['message'])) {
                $message = (string) $data['message'];
            }
        }

        parent::__construct(503, $message, $previous, $headers, $code);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
