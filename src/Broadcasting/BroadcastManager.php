<?php

declare(strict_types=1);

namespace Libxa\Broadcasting;

use Libxa\Foundation\Application;

class BroadcastManager
{
    /**
     * Managed broadcaster instances.
     */
    protected array $broadcasters = [];

    /**
     * Create a new broadcast manager instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Get a broadcaster instance by name.
     */
    public function connection(?string $name = null): Broadcaster
    {
        $name = $name ?: $this->getDefaultDriver();

        if (isset($this->broadcasters[$name])) {
            return $this->broadcasters[$name];
        }

        return $this->broadcasters[$name] = $this->resolve($name);
    }

    /**
     * Resolve the given broadcaster.
     */
    protected function resolve(string $name): Broadcaster
    {
        $config = $this->app->config("broadcasting.connections.{$name}");

        $driverMethod = 'create' . ucfirst($config['driver'] ?? $name) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new \InvalidArgumentException("Broadcaster driver [{$name}] is not supported.");
    }

    /**
     * Create a new Log broadcaster driver.
     */
    protected function createLogDriver(array $config): Broadcaster
    {
        return new LogBroadcaster($this->app);
    }

    /**
     * Create a new Libxa broadcaster driver.
     */
    protected function createLibxaDriver(array $config): Broadcaster
    {
        return new LibxaBroadcaster($this->app, $config);
    }

    /**
     * Create a new Pusher broadcaster driver.
     */
    protected function createPusherDriver(array $config): Broadcaster
    {
        return new PusherBroadcaster($this->app, $config);
    }

    /**
     * Get the default broadcast driver name.
     */
    protected function getDefaultDriver(): string
    {
        return $this->app->config('broadcasting.default', 'log');
    }

    /**
     * Broadcast an event.
     */
    public function event(ShouldBroadcast $event): void
    {
        $this->connection()->broadcast(
            $event->broadcastOn(),
            $event->broadcastAs(),
            $event->broadcastWith()
        );
    }
}
