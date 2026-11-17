<?php

declare(strict_types=1);

namespace ZF1\Exceptions;

use Throwable;

final class AccessDeniedHttpException extends HttpException
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
        parent::__construct(403, $message, $previous, $headers, $code);
    }
}
