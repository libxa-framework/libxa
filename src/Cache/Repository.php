<?php

declare(strict_types=1);

namespace Libxa\Cache;

use Closure;

class Repository
{
    /**
     * Create a new cache repository instance.
     */
    public function __construct(protected Store $store)
    {
    }

    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key);

        return is_null($value) ? value($default) : $value;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function put(string $key, mixed $value, int $seconds): bool
    {
        return $this->store->put($key, $value, $seconds);
    }

    /**
     * Retrieve an item from the cache and delete it.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return tap($this->get($key, $default), function () use ($key) {
            $this->forget($key);
        });
    }

    /**
     * Store an item in the cache indefinitely.
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->store->forever($key, $value);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     */
    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        $value = $this->get($key);

        if (! is_null($value)) {
            return $value;
        }

        $this->put($key, $value = $callback(), $seconds);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        $value = $this->get($key);

        if (! is_null($value)) {
            return $value;
        }

        $this->forever($key, $value = $callback());

        return $value;
    }

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool
    {
        return $this->store->forget($key);
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        return $this->store->flush();
    }

    /**
     * Increment the value of an item in the cache.
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store->increment($key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store->decrement($key, $value);
    }

    /**
     * Determine if an item exists in the cache.
     */
    public function has(string $key): bool
    {
        return ! is_null($this->get($key));
    }
}
