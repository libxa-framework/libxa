<?php

declare(strict_types=1);

namespace Libxa\Blade;

/**
 * BladeStack — runtime support for @push / @stack directives.
 */
class BladeStack
{
    protected static array $stacks = [];

    public static function push(string $name, string $content): void
    {
        static::$stacks[$name][] = $content;
    }

    public static function get(string $name): string
    {
        return implode("\n", static::$stacks[$name] ?? []);
    }

    public static function flush(): void
    {
        static::$stacks = [];
    }
}
