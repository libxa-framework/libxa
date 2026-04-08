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
            
            // Register default connection from config
            $config = $app->config('database.connections.' . $app->config('database.default', 'sqlite'), []);
            
            if ($config) {
                // If it's the first time, ConnectionPool might need to be primed
            }
            
            return $pool;
        });

        $this->app->alias(ConnectionPool::class, 'db.pool');
    }
}
