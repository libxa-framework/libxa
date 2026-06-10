<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Atlas\Connection\ConnectionPool;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectionPool::class, function ($app) {
            $pool = ConnectionPool::getInstance();

            // Build the full connections config map and configure the pool
            $dbConfig     = $app->config('database', []);
            $default      = $dbConfig['default'] ?? 'sqlite';
            $connections  = $dbConfig['connections'] ?? [];

            // Normalise: set the resolved default connection as 'default'
            if (isset($connections[$default])) {
                $connections['default'] = $connections[$default];
            }

            $pool->configure($connections);

            return $pool;
        });

        $this->app->alias(ConnectionPool::class, 'db.pool');
    }
}
