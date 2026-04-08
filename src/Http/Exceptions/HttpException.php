<?php

declare(strict_types=1);

namespace Libxa\Http\Exceptions;

use Exception;

/**
 * Basic HTTP Exception for abort() signaling.
 */
class HttpException extends Exception
{
    public function __construct(
        protected int $statusCode,
        string $message = '',
        \Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
