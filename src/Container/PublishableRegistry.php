<?php

declare(strict_types=1);

namespace Libxa\Container;

use Libxa\Foundation\Application;

/**
 * Registry of publishable assets from packages.
 */
class PublishableRegistry
{
    protected static array $publishables = [];

    public static function register(string $provider, array $paths, string $group): void
    {
        static::$publishables[$provider][$group] = $paths;
    }

    public static function all(): array
    {
        return static::$publishables;
    }

    public static function forGroup(string $group): array
    {
        $result = [];

        foreach (static::$publishables as $provider => $groups) {
            if (isset($groups[$group])) {
                $result = array_merge($result, $groups[$group]);
            }
        }

        return $result;
    }
}
