<?php

declare(strict_types=1);

namespace Libxa\Providers;

use Libxa\Container\ServiceProvider;
use Libxa\Storage\Storage;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('storage', function ($app) {
            return new \Libxa\Storage\StorageManager($app);
        });

        $this->app->alias('storage', \Libxa\Storage\StorageManager::class);
    }
}
