<?php

declare(strict_types=1);

namespace Libxa\Cache;

class FileStore implements Store
{
    /**
     * Create a new file-based cache store.
     */
    public function __construct(protected string $directory, protected string $prefix = '')
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $data = file_get_contents($path);
        
        try {
            $expire = (int) substr($data, 0, 10);
        } catch (\Exception $e) {
            return null;
        }

        if (time() >= $expire) {
            $this->forget($key);
            return null;
        }

        return unserialize(substr($data, 10));
    }

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function put(string $key, mixed $value, int $seconds): bool
    {
        $path = $this->path($key);
        $expire = time() + $seconds;
        $data = $expire . serialize($value);

        return file_put_contents($path, $data, LOCK_EX) !== false;
    }

    /**
     * Increment the value of an item in the cache.
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        $current = (int) $this->get($key) ?: 0;
        $new = $current + $value;
        $this->forever($key, $new);
        return $new;
    }

    /**
     * Decrement the value of an item in the cache.
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 31536000 * 10); // 10 years
    }

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        if (is_dir($this->directory)) {
            foreach (glob(rtrim($this->directory, '/') . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        return true;
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the full path for the given cache key.
     */
    protected function path(string $key): string
    {
        $hash = sha1($this->prefix . $key);
        return rtrim($this->directory, '/') . '/' . $hash;
    }
}
