<?php

namespace Libxa\Multitenancy;

use Libxa\Foundation\Application;
use Libxa\Http\Request;

class TenancyManager
{
    protected Application $app;
    protected ?string $currentTenant = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Boot the tenancy layer for the current request.
     */
    public function initialize(Request $request): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $resolverName = config('tenancy.resolver', 'subdomain');
        $resolver = $this->getResolver($resolverName);

        if ($resolver) {
            $this->currentTenant = $resolver->resolve($request);
            if ($this->currentTenant) {
                $this->applyTenantContext($this->currentTenant);
            }
        }
    }

    public function isEnabled(): bool
    {
        return config('tenancy.enabled', false) || $this->app->env('TENANCY_ENABLED') === 'true';
    }

    public function getTenant(): ?string
    {
        return $this->currentTenant;
    }

    protected function getResolver(string $name): ?Contracts\TenantResolverContract
    {
        $resolvers = [
            'subdomain' => Resolvers\SubdomainResolver::class,
            'header'    => Resolvers\HeaderResolver::class,
        ];

        $class = $resolvers[$name] ?? null;

        if ($class && class_exists($class)) {
            return new $class();
        }

        return null;
    }

    protected function applyTenantContext(string $tenant): void
    {
        $strategy = config('tenancy.strategy', $this->app->env('TENANCY_STRATEGY', 'separate_database'));

        if ($strategy === 'separate_database') {
            // Pivot the database connection configuration seamlessly
            $dbName = $tenant . '_db';
            $configSvc = $this->app->make('config');
            $configSvc->set('database.connections.tenant', [
                'driver'   => $this->app->env('DB_DRIVER', 'sqlite'),
                'database' => $this->app->env('DB_DRIVER', 'sqlite') === 'sqlite' 
                    ? $this->app->storagePath("database/{$dbName}.sqlite") 
                    : $dbName,
                'host'     => $this->app->env('DB_HOST', '127.0.0.1'),
                'username' => $this->app->env('DB_USERNAME', 'root'),
                'password' => $this->app->env('DB_PASSWORD', ''),
            ]);
            
            // Re-route the Atlas ORM default connection
            $configSvc->set('database.default', 'tenant');

            
            // Reconnect the DB pool if necessary
            $connPool = \Libxa\Atlas\Connection\ConnectionPool::getInstance();
            // In a complete implementation, this would switch the active PDO binding dynamically
        } elseif ($strategy === 'prefix') {
            // Apply a global table prefix configuration for Atlas Models
            $this->app->make('config')->set('database.prefix', $tenant . '_');
        }

        // Apply isolation configs based on 'isolate' preferences
        $isolated = config('tenancy.isolate', ['database', 'cache', 'storage']);
        if (in_array('cache', $isolated)) {
            $this->app->make('config')->set('cache.prefix', $tenant . '_cache_');
        }
    }
}
