<?php

declare(strict_types=1);

namespace Libxa\Queue;

use Libxa\Foundation\Application;

class QueueManager
{
    /**
     * Registered queue drivers.
     */
    protected array $drivers = [];

    /**
     * Create a new queue manager instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Get a queue driver instance by name.
     */
    public function connection(?string $name = null): Queue
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    /**
     * Resolve the given driver by name.
     */
    protected function resolve(string $name): Queue
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Queue connection [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new \InvalidArgumentException("Queue driver [{$config['driver']}] is not supported.");
    }

    /**
     * Create an instance of the database queue driver.
     */
    protected function createDatabaseDriver(array $config): Queue
    {
        return new DatabaseQueue(
            $this->app,
            $config['table']   ?? 'jobs',
            $config['queue']   ?? 'default'
        );
    }

    /**
     * Create an instance of the sync queue driver.
     */
    protected function createSyncDriver(array $config): Queue
    {
        return new SyncQueue();
    }

    /**
     * Get the queue connection configuration.
     */
    protected function getConfig(string $name): ?array
    {
        return $this->app->config("queue.connections.{$name}");
    }

    /**
     * Get the default queue driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app->config('queue.default', 'database');
    }

    /**
     * Get a FiberWorker to process jobs concurrently.
     */
    public function fiber(): FiberWorker
    {
        return new FiberWorker();
    }

    /**
     * Dynamically call the default connection instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}
