<?php

declare(strict_types=1);

namespace Libxa\Blade;

/**
 * Shared data store — works like View::share() in Laravel.
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
