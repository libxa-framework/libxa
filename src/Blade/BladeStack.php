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

    public static function prepend(string $name, string $content): void
    {
        static::$stacks[$name] = static::$stacks[$name] ?? [];
        array_unshift(static::$stacks[$name], $content);
    }

    public static function get(string $name): string
    {
        return implode("\n", static::$stacks[$name] ?? []);
    }

    /**
     * Clear all stacks. Called automatically by BladeEngine before each
     * *top-level* render() so that content pushed by one request can
     * never leak into the response of another — critical under a
     * persistent-process runtime (Workerman/reactive server) where this
     * class's static state would otherwise survive across requests.
     */
    public static function flush(): void
    {
        static::$stacks = [];
    }
}
