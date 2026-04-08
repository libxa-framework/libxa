<?php

declare(strict_types=1);

namespace Libxa\Auth;

use Libxa\Foundation\Application;

/**
 * Auth Manager
 *
 * Manages multiple authentication guards and resolves them
 * using the container.
 */
class AuthManager
{
    /** The resolved guard instances */
    protected array $guards = [];

    /** Custom guard creators */
    protected array $customCreators = [];

    public function __construct(protected Application $app) {}

    /**
     * Get a guard instance by name.
     */
    public function guard(?string $name = null): Guard
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->guards[$name] ??= $this->resolve($name);
    }

    /**
     * Resolve the given guard.
     */
    protected function resolve(string $name): Guard
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($name, $config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->$driverMethod($name, $config);
        }

        throw new \InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not supported.");
    }

    /**
     * Create a session-based authentication guard.
     */
    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        return new SessionGuard($name, $provider, $this->app->make('session'));
    }

    /**
     * Create a robust LibxaSecure authentication guard.
     */
    protected function createLibxasecureDriver(string $name, array $config): Guards\LibxaSecureGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        return new Guards\LibxaSecureGuard($provider, $this->app->make('request'));
    }

    /**
     * Create a token-based authentication guard.
     */
    protected function createTokenDriver(string $name, array $config): TokenGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        return new TokenGuard(
            $provider,
            $this->app->make('request'),
            $config['input_key'] ?? 'api_token',
            $config['storage_key'] ?? 'api_token'
        );
    }

    /**
     * Create the user provider instance.
     */
    public function createUserProvider(?string $provider = null): UserProvider
    {
        $config = $this->app->config('auth.providers.' . ($provider ?: $this->getDefaultProvider()));

        if ($config['driver'] === 'database') {
            return new DBUserProvider($config['table'] ?? 'users');
        }

        throw new \InvalidArgumentException("Authentication user provider driver [{$config['driver']}] is not supported.");
    }

    /**
     * Get the auth configuration for the guard.
     */
    protected function getConfig(string $name): ?array
    {
        return $this->app->config("auth.guards.{$name}");
    }

    /**
     * Get the default authentication driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->app->config('auth.defaults.guard', 'web');
    }

    /**
     * Get the default user provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->app->config('auth.defaults.provider', 'users');
    }

    /**
     * Dynamically call the default guard instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->$method(...$parameters);
    }
}
