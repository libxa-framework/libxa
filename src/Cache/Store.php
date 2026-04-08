<?php

declare(strict_types=1);

namespace Libxa\Cache;

interface Store
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key): mixed;

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function put(string $key, mixed $value, int $seconds): bool;

    /**
     * Increment the value of an item in the cache.
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Decrement the value of an item in the cache.
     */
    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever(string $key, mixed $value): bool;

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool;

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string;
}
