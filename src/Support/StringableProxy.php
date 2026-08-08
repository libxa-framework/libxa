<?php

declare(strict_types=1);

namespace Libxa\Support;

/**
 * Fluent string wrapper (clone of Laravel's Stringable).
 */
class StringableProxy
{
    public function __construct(protected string $value) {}

    public function __call(string $method, array $args): mixed
    {
        $result = Str::$method($this->value, ...$args);
        return is_string($result) ? new static($result) : $result;
    }

    public function __toString(): string { return $this->value; }
    public function toString(): string   { return $this->value; }
    public function value(): string      { return $this->value; }
}
