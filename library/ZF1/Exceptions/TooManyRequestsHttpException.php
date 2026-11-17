<?php

declare(strict_types=1);

namespace ZF1\Exceptions;

use Throwable;

class TooManyRequestsHttpException extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct(429, $message, $previous, $headers, $code);
    }
}
