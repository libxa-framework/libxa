<?php

declare(strict_types=1);

namespace Libxa\Http\Exceptions;

use Exception;

/**
 * Basic HTTP Exception for abort() signaling.
 *
 * The $headers constructor argument used to be accepted and then thrown away,
 * so a 429 could never carry Retry-After and a 401 could never carry
 * WWW-Authenticate. It is stored and exposed now.
 *
 * The $previous parameter also had an implicit-nullable type
 * (`\Throwable $previous = null`), which PHP 8.4 deprecates.
 */
class HttpException extends Exception
{
    public function __construct(
        protected int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        protected array $headers = [],
        int $code = 0
    ) {
        parent::__construct($message, $code, $previous);
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

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }
}
