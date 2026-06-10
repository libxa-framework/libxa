<?php

declare(strict_types=1);

namespace Libxa\Validation;

use Libxa\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when request validation fails.
 */
class ValidationException extends RuntimeException
{
    public function __construct(
        protected MessageBag $errors,
        string $message = 'The given data was invalid.'
    ) {
        parent::__construct($message);
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * Convert the exception to a JSON response (for API requests).
     */
    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'errors'  => $this->errors->toArray(),
        ], 422);
    }
}
