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
     * Driver factories registered from outside this class.
     *
     * @var array<string, \Closure>
     */
    protected array $customDrivers = [];

    /**
     * Create a new broadcast manager instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Register a broadcaster a package provides.
     *
     * Without this, a driver had to be a `create<Name>Driver` method on this
     * class, so the only way to add one was to edit the framework — which
     * makes broadcasting the one part of the system a package cannot extend.
     *
     * The callback receives the connection's config and returns a Broadcaster.
     */
    public function extend(string $driver, \Closure $factory): static
    {
        $this->customDrivers[$driver] = $factory;

        // A driver registered after something already resolved it must take
        // effect: providers boot in an order nobody controls, and a cached
        // instance from before would silently win.
        unset($this->broadcasters[$driver]);

        return $this;
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
        $config = (array) ($this->app->config("broadcasting.connections.{$name}") ?? []);

        $driver = (string) ($config['driver'] ?? $name);

        // Registered drivers win over the built-ins, so an application can
        // replace one without the framework having to know it did.
        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($config, $this->app);
        }

        $driverMethod = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new \InvalidArgumentException(
            "Broadcaster driver [{$driver}] is not supported. Registered drivers: "
            . implode(', ', $this->availableDrivers()),
        );
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
     * Every driver name that would resolve.
     *
     * Used in the error above: "not supported" without saying what is turns a
     * typo into a hunt through the framework.
     *
     * @return list<string>
     */
    public function availableDrivers(): array
    {
        $built = [];

        foreach (get_class_methods($this) as $method) {
            if (preg_match('/^create(.+)Driver$/', $method, $matches) === 1) {
                $built[] = lcfirst($matches[1]);
            }
        }

        return array_values(array_unique(array_merge($built, array_keys($this->customDrivers))));
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
