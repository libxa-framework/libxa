<?php

declare(strict_types=1);

namespace Libxa\Storage;

use Libxa\Foundation\Application;

/**
 * Storage Facade
 */
class Storage
{
    /**
     * Get the storage manager instance.
     */
    protected static function manager(): StorageManager
    {
        return Application::getInstance()->make('storage');
    }

    public static function put(string $path, string $content): bool
    {
        return static::manager()->put($path, $content);
    }

    public static function get(string $path): ?string
    {
        return static::manager()->get($path);
    }

    public static function exists(string $path): bool
    {
        return static::manager()->exists($path);
    }

    public static function delete(string|array $paths): bool
    {
        return static::manager()->delete(...(is_array($paths) ? $paths : func_get_args()));
    }

    public static function move(string $from, string $to): bool
    {
        return static::manager()->move($from, $to);
    }

    public static function copy(string $from, string $to): bool
    {
        return static::manager()->copy($from, $to);
    }

    public static function mimeType(string $path): ?string
    {
        return static::manager()->mimeType($path);
    }

    public static function files(string $directory = '', bool $recursive = false): array
    {
        return static::manager()->files($directory, $recursive);
    }

    public static function url(string $path): string
    {
        return static::manager()->url($path);
    }

    public static function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        return static::manager()->temporaryUrl($path, $expiration);
    }

    /**
     * Get a disk instance (for future multi-disk support).
     */
    public static function disk(string $name = 'local'): StorageManager
    {
        return static::manager();
    }
}
