<?php

declare(strict_types=1);

namespace Libxa\Cache;

use Libxa\Foundation\Application;

class CacheManager
{
    /**
     * Registered cache stores.
     */
    protected array $stores = [];

    /**
     * Create a new cache manager instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Get a cache store instance by name.
     */
    public function store(?string $name = null): Repository
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->stores[$name] ??= $this->resolve($name);
    }

    /**
     * Resolve the given store by name.
     */
    protected function resolve(string $name): Repository
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Cache store [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new \InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * Create an instance of the file cache driver.
     */
    protected function createFileDriver(array $config): Repository
    {
        $path = $config['path'] ?? $this->app->storagePath('framework/cache');
        
        return new Repository(new FileStore($path));
    }

    /**
     * Get the cache connection configuration.
     */
    protected function getConfig(string $name): ?array
    {
        return $this->app->config("cache.stores.{$name}");
    }

    /**
     * Get the default cache driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app->config('cache.default', 'file');
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->$method(...$parameters);
    }
}
