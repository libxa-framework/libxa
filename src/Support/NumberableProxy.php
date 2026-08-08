<?php

declare(strict_types=1);

namespace Libxa\Support;

use NumberFormatter;

/**
 * Fluent number wrapper.
 */
class NumberableProxy
{
    public function __construct(protected float|int $value) {}

    public function __call(string $method, array $args): mixed
    {
        $result = Number::$method($this->value, ...$args);
        return (is_float($result) || is_int($result)) ? new static($result) : $result;
    }

    public function value(): float|int { return $this->value; }
    public function toString(): string  { return (string) $this->value; }
    public function __toString(): string { return (string) $this->value; }
}
