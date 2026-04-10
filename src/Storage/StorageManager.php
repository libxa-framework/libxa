<?php

declare(strict_types=1);

namespace Libxa\Storage;

use Libxa\Foundation\Application;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class StorageManager
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Get the base storage path.
     */
    protected function storagePath(string $path = ''): string
    {
        return $this->app->storagePath('app/' . ltrim($path, '/'));
    }

    /**
     * Store a file on a given path.
     */
    public function put(string $path, string $content): bool
    {
        $fullPath = $this->storagePath($path);
        
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($fullPath, $content) !== false;
    }

    /**
     * Get the content of a file.
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->storagePath($path);
        
        if (!$this->exists($path)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($this->storagePath($path));
    }

    /**
     * Delete a file.
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();

        $success = true;

        foreach ($paths as $path) {
            if ($this->exists($path)) {
                if (!unlink($this->storagePath($path))) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool
    {
        if (!$this->exists($from)) {
            return false;
        }

        $toPath = $this->storagePath($to);
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return rename($this->storagePath($from), $toPath);
    }

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool
    {
        if (!$this->exists($from)) {
            return false;
        }

        $toPath = $this->storagePath($to);
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return copy($this->storagePath($from), $toPath);
    }

    /**
     * Get the mime type of a file.
     */
    public function mimeType(string $path): ?string
    {
        if (!$this->exists($path)) {
            return null;
        }

        return mime_content_type($this->storagePath($path)) ?: null;
    }

    /**
     * Get an array of all files in a directory.
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $fullPath = $this->storagePath($directory);
        
        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];

        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = str_replace($this->storagePath() . '/', '', $file->getPathname());
                }
            }
        } else {
            foreach (scandir($fullPath) as $file) {
                if ($file !== '.' && $file !== '..' && is_file($fullPath . '/' . $file)) {
                    $files[] = ltrim($directory . '/' . $file, '/');
                }
            }
        }

        return $files;
    }

    /**
     * Get the public URL for a file.
     */
    public function url(string $path): string
    {
        if (str_starts_with($path, 'public/')) {
            return '/storage/' . ltrim(substr($path, 7), '/');
        }

        return '/storage/' . ltrim($path, '/');
    }

    /**
     * Get a temporary URL for a file (Placeholder implementation).
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration): string
    {
        // For local storage, we just return the URL with a dummy signature for now
        // A real implementation would require a signed route.
        $timestamp = $expiration->getTimestamp();
        $signature = hash_hmac('sha256', $path . $timestamp, $this->app->env('APP_KEY', 'secret'));

        return $this->url($path) . '?expires=' . $timestamp . '&signature=' . $signature;
    }
}
