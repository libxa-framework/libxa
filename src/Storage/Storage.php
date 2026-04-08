<?php

declare(strict_types=1);

namespace Libxa\Storage;

use Libxa\Foundation\Application;

class Storage
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Get the base storage path.
     */
    protected function storagePath(string $path = ''): string
    {
        return $this->app->basePath('src/storage/app/' . ltrim($path, '/'));
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
    public function delete(string $path): bool
    {
        if (!$this->exists($path)) {
            return false;
        }

        return unlink($this->storagePath($path));
    }

    /**
     * Get the public URL for a file.
     */
    public function url(string $path): string
    {
        // For public storage (storage/app/public), the URL targets /storage/path
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return '/' . 'storage/' . ltrim($path, '/');
    }
}
