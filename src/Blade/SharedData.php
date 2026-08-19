<?php

declare(strict_types=1);

namespace Libxa\Blade;

/**
 * Shared data store: values made available to every view.
 */
class SharedData
{
    protected static array $data = [];

    public static function set(string $key, mixed $value): void
    {
        static::$data[$key] = $value;
    }

    public static function get(?string $key = null): mixed
    {
        return $key !== null ? (static::$data[$key] ?? null) : static::$data;
    }

    public static function all(): array { return static::$data; }
}
