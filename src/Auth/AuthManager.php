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
    protected array $guards = [];
    protected array $customCreators = [];

    public function __construct(protected Application $app) {}

    public function guard(?string $name = null): Guard
    {
        $name = $name ?: $this->getDefaultDriver();
        return $this->guards[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): Guard
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($name, $config);
        }

        // Normalise driver name: 'libxasecure' or 'Libxasecure' → createLibxasecureDriver
        $driverMethod = 'create' . str_replace('_', '', ucwords(strtolower($config['driver']), '_')) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->$driverMethod($name, $config);
        }

        throw new \InvalidArgumentException("Auth driver [{$config['driver']}] for guard [{$name}] is not supported.");
    }

    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        return new SessionGuard($name, $provider, $this->app->make('session'));
    }

    protected function createLibxasecureDriver(string $name, array $config): Guards\LibxaSecureGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        return new Guards\LibxaSecureGuard($provider, $this->app->make('request'));
    }

    protected function createTokenDriver(string $name, array $config): TokenGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);
        return new TokenGuard(
            $provider,
            $this->app->make('request'),
            $config['input_key']   ?? 'api_token',
            $config['storage_key'] ?? 'api_token'
        );
    }

    public function createUserProvider(?string $provider = null): UserProvider
    {
        $providerName = $provider ?: $this->getDefaultProvider();
        $config       = $this->app->config('auth.providers.' . $providerName, []);
        $driver       = $config['driver'] ?? 'database';

        if ($driver === 'database') {
            $table = $config['table'] ?? 'users';
            $model = $config['model'] ?? \stdClass::class;
            return new DBUserProvider($table, $model);
        }

        throw new \InvalidArgumentException("Authentication user provider driver [{$driver}] is not supported.");
    }

    protected function getConfig(string $name): ?array
    {
        return $this->app->config("auth.guards.{$name}");
    }

    public function getDefaultDriver(): string
    {
        return $this->app->config('auth.defaults.guard', 'web');
    }

    public function getDefaultProvider(): string
    {
        return $this->app->config('auth.defaults.provider', 'users');
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->$method(...$parameters);
    }
}
