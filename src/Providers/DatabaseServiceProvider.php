<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Atlas\Connection\ConnectionPool;
use Libxa\Atlas\Migrations\Migrator;

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

        // The migrator has to be a shared instance, because a package's
        // migrations are registered by its service provider calling
        // loadMigrationsFrom() during boot, long before anything runs them.
        //
        // Without this binding that method silently did nothing: it is
        // guarded by `$this->app->has('migrator')`, nothing ever bound
        // 'migrator', so every package that followed the documented API
        // shipped a migration that could never run and said so nowhere.
        $this->app->singleton('migrator', function ($app) {
            $migrator = new Migrator();

            $migrator->addPath($app->basePath('src/database/migrations'));

            return $migrator;
        });

        $this->app->alias('migrator', Migrator::class);
    }
}
