<?php

declare(strict_types=1);

namespace ZF1\Exceptions;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    protected int $statusCode;

    /**
     * @var array<string, string>
     */
    protected array $headers;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        if ($message === '' && class_exists(\ZF1\View\ErrorRenderer::class)) {
            $message = \ZF1\View\ErrorRenderer::getDefaultMessage($statusCode);
        }

        parent::__construct($message, $code ?: $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
