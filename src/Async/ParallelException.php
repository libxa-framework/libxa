<?php

declare(strict_types=1);

namespace Libxa\Async;

use RuntimeException;
use Throwable;

/**
 * Aggregates the failures from a Parallel::run() batch, so one failing task
 * no longer silently discards the results of the tasks that succeeded.
 */
class ParallelException extends RuntimeException
{
    /**
     * @param array<string, Throwable> $errors task key => exception
     */
    public function __construct(protected array $errors)
    {
        $summary = implode(', ', array_map(
            static fn(string $key, Throwable $e): string => "{$key}: " . $e->getMessage(),
            array_keys($errors),
            $errors
        ));

        parent::__construct(
            count($errors) . ' parallel task(s) failed: ' . $summary,
            0,
            reset($errors) ?: null
        );
    }

    /**
     * @return array<string, Throwable>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function failedKeys(): array
    {
        return array_keys($this->errors);
    }
}
